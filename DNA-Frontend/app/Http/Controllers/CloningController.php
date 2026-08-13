<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloningRequest;
use App\Models\CloningPlan;
use App\Services\BackendException;
use App\Services\DnaBackendClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CloningController extends Controller
{
    public function __construct(private readonly DnaBackendClient $backend)
    {
    }

    public function index(): mixed
    {
        return view('cloning.index', [
            'recent' => CloningPlan::latest()->limit(5)->get(),
        ]);
    }

    public function store(CloningRequest $request): RedirectResponse
    {
        try {
            $result = $this->backend->cloning($request->parameters());
        } catch (BackendException $exception) {
            return $this->backendFailed($exception, 'sequence');
        }

        $plan = CloningPlan::create([
            'label' => $request->string('label')->toString() ?: null,
            'template_length' => strlen($request->cleanSequence()),
            'panel' => $request->string('panel')->toString(),
            'circular' => $request->boolean('circular'),
            'forward_enzyme' => $request->string('forward_enzyme')->toString() ?: null,
            'reverse_enzyme' => $request->string('reverse_enzyme')->toString() ?: null,
            'succeeded' => (bool) data_get($result, 'ok', false),
            'result' => $result,
        ]);

        return redirect()->route('cloning.show', ['plan' => $plan->id]);
    }

    public function show(CloningPlan $plan): mixed
    {
        return view('cloning.show', ['plan' => $plan]);
    }

    /**
     * The primers as a supplier's order form wants them.
     *
     * Exports the *tailed* sequence when there is one. Ordering the binding
     * region alone is the specific mistake worth engineering against: the two
     * sequences look almost identical on screen, and only one of them clones.
     */
    public function csv(CloningPlan $plan): StreamedResponse
    {
        $rows = $plan->order();

        return $this->csvDownload('primers-' . $plan->id . '.csv', function ($handle) use ($rows) {
            fputcsv($handle, [
                __('cloning.export.name'),
                __('cloning.export.sequence'),
                __('cloning.export.length'),
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [$row['name'], $row['sequence'], $row['length']]);
            }
        });
    }

    /** The amplified region, as a file the next tool can read. */
    public function fasta(CloningPlan $plan): Response
    {
        $amplicon = $plan->amplicon();
        abort_if(($amplicon['sequence'] ?? '') === '', 404);

        $header = '>amplicon_' . substr($plan->id, 0, 8)
            . ' length=' . $amplicon['length']
            . ' region=' . $amplicon['start'] . '-' . $amplicon['end'];

        $body = $header . "\n" . implode("\n", str_split($amplicon['sequence'], 60)) . "\n";

        return $this->textDownload('amplicon-' . $plan->id . '.fasta', $body);
    }

    public function json(CloningPlan $plan): JsonResponse
    {
        return $this->jsonDownload($plan->result, 'cloning-' . $plan->id . '.json');
    }
}
