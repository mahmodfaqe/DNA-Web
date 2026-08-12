<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemoryRequest;
use App\Models\MemoryDesign;
use App\Services\BackendException;
use App\Services\DnaBackendClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class MemoryController extends Controller
{
    public function __construct(private readonly DnaBackendClient $backend)
    {
    }

    public function index(): mixed
    {
        return view('memory.index', [
            'recent' => MemoryDesign::latest()->limit(5)->get(),
        ]);
    }

    public function store(MemoryRequest $request): RedirectResponse
    {
        try {
            $result = $this->backend->memory($request->parameters());
        } catch (BackendException $exception) {
            return $this->backendFailed($exception, 'signal');
        }

        $design = MemoryDesign::create([
            'signal' => data_get($result, 'request.signal', $request->string('signal')->toString()),
            'chassis' => data_get($result, 'request.chassis', $request->string('chassis')->toString()),
            'architecture' => data_get($result, 'recommendation.architecture'),
            'hold_hours' => (int) round((float) data_get($result, 'request.hold_hours', 0)),
            'succeeded' => (bool) data_get($result, 'ok', false),
            'result' => $result,
        ]);

        return redirect()->route('memory.show', ['design' => $design->id]);
    }

    public function show(MemoryDesign $design): mixed
    {
        return view('memory.show', ['design' => $design]);
    }

    /**
     * The construct, as a file a synthesis order can be built from.
     *
     * Refused for a design that failed: a partial or unrecommended construct
     * is exactly the kind of file that gets ordered by accident six months
     * later, when nobody remembers why the page said no.
     */
    public function fasta(MemoryDesign $design): Response
    {
        abort_unless($design->succeeded, 404);

        return $this->textDownload('memory-' . $design->id . '.fasta', $design->fasta());
    }

    public function json(MemoryDesign $design): JsonResponse
    {
        return $this->jsonDownload($design->result, 'memory-' . $design->id . '.json');
    }
}
