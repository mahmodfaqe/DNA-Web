<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompileRequest;
use App\Models\Circuit;
use App\Services\BackendException;
use App\Services\DnaBackendClient;
use App\Support\ErrorTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Streamed for the same reason the CSV export is: the compiled construct
     * is handed straight to the browser rather than held in memory first.
     */
    public function fasta(Circuit $circuit): StreamedResponse
    {
        abort_unless($circuit->succeeded, 404);

        return response()->streamDownload(
            fn () => print $circuit->fasta(),
            'circuit-' . $circuit->id . '.fasta',
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    public function json(Circuit $circuit): JsonResponse
    {
        return response()->json($circuit->compiled)
            ->header('Content-Disposition', 'attachment; filename="circuit-' . $circuit->id . '.json"');
    }
}
