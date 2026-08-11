<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompileRequest;
use App\Models\Circuit;
use App\Services\BackendException;
use App\Services\DnaBackendClient;
use App\Support\ErrorTranslator;
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

    /**
     * A sentence the compiler could not parse is still stored and still gets a
     * URL. The diagnostics explaining *why* it failed are the most instructive
     * output the tool produces, and throwing them away with a redirect-back
     * would waste them.
     */
    public function store(CompileRequest $request): RedirectResponse
    {
        try {
            $compiled = $this->backend->compile($request->string('description')->toString());
        } catch (BackendException $exception) {
            return back()
                ->withInput()
                ->withErrors(['description' => ErrorTranslator::translate($exception)]);
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

    /**
     * The FASTA is served exactly as the compiler produced it, comment lines
     * included: those lines carry the source sentence and the synthesis warning,
     * so the file stays self-explanatory once it leaves the browser.
     */
    public function fasta(Circuit $circuit): Response
    {
        abort_unless($circuit->succeeded, 404);

        return response($circuit->fasta(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="circuit-' . $circuit->id . '.fasta"',
        ]);
    }

    public function json(Circuit $circuit): JsonResponse
    {
        return response()->json($circuit->compiled)
            ->header('Content-Disposition', 'attachment; filename="circuit-' . $circuit->id . '.json"');
    }
}
