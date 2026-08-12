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
}
