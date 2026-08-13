<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompileRequest;
use App\Models\Circuit;
use App\Services\BackendException;
use App\Services\DnaBackendClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class CompilerController extends Controller
{
    /**
     * Format name to file extension.
     *
     * Mirrored from the backend's own table rather than fetched, because it
     * decides a route parameter and a 404 is the right answer for a format that
     * does not exist. The backend validates it again and refuses the rest.
     */
    public const EXPORT_FORMATS = ['sbol' => 'xml', 'genbank' => 'gb'];

    public function __construct(private readonly DnaBackendClient $backend)
    {
    }

    public function index(): mixed
    {
        return view('compiler.index', [
            'recent' => Circuit::latest()->limit(5)->get(),
        ]);
    }

    public function store(CompileRequest $request): RedirectResponse
    {
        try {
            $compiled = $this->backend->compile($request->string('description')->toString());
        } catch (BackendException $exception) {
            return $this->backendFailed($exception, 'description');
        }

        $circuit = Circuit::create([
            'source_text' => $request->string('description')->toString(),
            'language' => data_get($compiled, 'specification.language', 'unknown'),
            'expression' => data_get($compiled, 'expression'),
            'succeeded' => (bool) data_get($compiled, 'ok', false),
            'compiled' => $compiled,
        ]);

        return redirect()->route('compiler.show', ['circuit' => $circuit->id]);
    }

    public function show(Circuit $circuit): mixed
    {
        return view('compiler.show', ['circuit' => $circuit]);
    }

    /** The assembled circuit, as a file a synthesis order can be built from. */
    public function fasta(Circuit $circuit): Response
    {
        abort_unless($circuit->succeeded, 404);

        return $this->textDownload('circuit-' . $circuit->id . '.fasta', $circuit->fasta());
    }

    public function json(Circuit $circuit): JsonResponse
    {
        return $this->jsonDownload($circuit->compiled, 'circuit-' . $circuit->id . '.json');
    }

    /**
     * The circuit in a format another design tool can read.
     *
     * FASTA carries bases and nothing else, so a design that leaves as FASTA
     * has lost the fact that those 22 bases are a BioBrick prefix and the next
     * 200 are a promoter. SBOL keeps that structure for the design ecosystem;
     * GenBank keeps it for the software on a student's own laptop.
     */
    public function export(Circuit $circuit, string $format): Response
    {
        abort_unless($circuit->succeeded, 404);
        abort_unless(array_key_exists($format, self::EXPORT_FORMATS), 404);

        try {
            $exported = $this->backend->exportCircuit($circuit->source_text, $format);
        } catch (BackendException $exception) {
            abort(503, $exception->getMessage());
        }

        $document = (string) data_get($exported, 'document', '');
        abort_if($document === '', 404);

        $extension = self::EXPORT_FORMATS[$format];

        return response($document, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="circuit-'
                . $circuit->id . '.' . $extension . '"',
        ]);
    }
}
