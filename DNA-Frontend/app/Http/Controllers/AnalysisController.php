<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeRequest;
use App\Models\Analysis;
use App\Services\BackendException;
use App\Services\DnaBackendClient;
use App\Support\ErrorTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalysisController extends Controller
{
    public function __construct(private readonly DnaBackendClient $backend)
    {
    }

    public function index(): mixed
    {
        return view('analysis.index', [
            'recent' => Analysis::latest()->limit(5)->get(),
        ]);
    }

    /**
     * Post / Redirect / Get.
     *
     * The previous version rendered results straight from the POST response, so
     * refreshing the page re-uploaded the file and re-ran the analysis, and
     * changing language threw the results away. Persisting first and redirecting
     * to a URL fixes both, and gives every result a link worth sharing.
     */
    public function store(AnalyzeRequest $request): RedirectResponse
    {
        $file = $request->file('fasta_file');

        try {
            $payload = $this->backend->analyze($file);
        } catch (BackendException $exception) {
            return back()
                ->withInput()
                ->withErrors(['fasta_file' => ErrorTranslator::translate($exception)]);
        }

        $analysis = Analysis::create([
            'filename' => $file->getClientOriginalName(),
            'size_bytes' => $file->getSize(),
            'checksum' => $payload['checksum'] ?? '',
            'gene_count' => count($payload['genes'] ?? []),
            'payload' => $payload,
        ]);

        return redirect()->route('analysis.show', ['analysis' => $analysis->id]);
    }

    public function show(Analysis $analysis): mixed
    {
        return view('analysis.show', ['analysis' => $analysis]);
    }

    public function print(Analysis $analysis): mixed
    {
        return view('analysis.print', ['analysis' => $analysis]);
    }

    public function json(Analysis $analysis): JsonResponse
    {
        return response()->json($analysis->payload)
            ->header('Content-Disposition', 'attachment; filename="dna-analysis-' . $analysis->id . '.json"');
    }

    /**
     * CSV is streamed rather than assembled in memory, and carries a UTF-8 BOM
     * so Excel opens Kurdish and Arabic headers correctly instead of mojibake.
     */
    public function csv(Analysis $analysis): StreamedResponse
    {
        $filename = 'dna-analysis-' . $analysis->id . '.csv';

        return response()->streamDownload(function () use ($analysis) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('analysis.table.id'),
                __('analysis.table.description'),
                __('analysis.table.length'),
                __('analysis.table.gc'),
                __('analysis.table.tm'),
                __('analysis.table.tm_method'),
                __('analysis.table.protein'),
                'A', 'T', 'C', 'G', 'N',
                __('analysis.table.ambiguous'),
            ]);

            foreach ($analysis->genes() as $gene) {
                $composition = $gene['base_composition'] ?? [];

                fputcsv($handle, [
                    $gene['id'] ?? '',
                    $gene['description'] ?? '',
                    $gene['length'] ?? 0,
                    $gene['gc_content'] ?? 0,
                    $gene['melting_temp']['value'] ?? '',
                    __('analysis.tm_methods.' . ($gene['melting_temp']['method'] ?? 'none')),
                    $gene['protein_length'] ?? 0,
                    $composition['A'] ?? 0,
                    $composition['T'] ?? 0,
                    $composition['C'] ?? 0,
                    $composition['G'] ?? 0,
                    $composition['N'] ?? 0,
                    $composition['ambiguous'] ?? 0,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        $backend = $this->backend->health();

        return response()->json([
            'frontend' => 'ok',
            'backend' => $backend['ok'] ? 'ok' : 'unavailable',
            'detail' => $backend['detail'],
        ], $backend['ok'] ? 200 : 503);
    }
}
