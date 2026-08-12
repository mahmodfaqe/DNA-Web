<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimulateRequest;
use App\Models\Simulation;
use App\Services\BackendException;
use App\Services\DnaBackendClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SimulatorController extends Controller
{
    public function __construct(private readonly DnaBackendClient $backend)
    {
    }

    public function index(): mixed
    {
        return view('simulator.index', [
            'recent' => Simulation::latest()->limit(5)->get(),
        ]);
    }

    /**
     * Post / Redirect / Get, and for this tool it matters more than elsewhere.
     *
     * Every run is random. If the result were rendered straight from the POST,
     * a refresh would silently replace it with a different one — same settings,
     * different numbers — and anybody comparing the two would be comparing the
     * dice. Persisting first means the URL keeps pointing at the run the reader
     * was actually looking at.
     */
    public function store(SimulateRequest $request): RedirectResponse
    {
        try {
            $result = $this->backend->simulate($request->parameters());
        } catch (BackendException $exception) {
            return $this->backendFailed($exception, 'preset');
        }

        $simulation = Simulation::create([
            'preset' => data_get($result, 'request.preset', $request->string('preset')->toString()),
            'cells' => (int) data_get($result, 'request.cells', 0),
            'minutes' => (int) data_get($result, 'request.minutes', 0),
            'seed' => (int) data_get($result, 'request.seed', 0),
            'succeeded' => (bool) data_get($result, 'ok', false),
            'result' => $result,
        ]);

        return redirect()->route('simulator.show', ['simulation' => $simulation->id]);
    }

    public function show(Simulation $simulation): mixed
    {
        return view('simulator.show', ['simulation' => $simulation]);
    }

    public function json(Simulation $simulation): JsonResponse
    {
        return $this->jsonDownload($simulation->result, 'simulation-' . $simulation->id . '.json');
    }

    /**
     * The sampled trajectories, one row per time point.
     *
     * Deliberately the ensemble mean and spread rather than every cell: the
     * full per-cell record is hundreds of thousands of numbers, and anyone who
     * needs that should re-run the simulation from the seed, which is printed
     * on the page and reproduces it exactly.
     */
    public function csv(Simulation $simulation): StreamedResponse
    {
        abort_unless($simulation->succeeded, 404);

        return $this->csvDownload('simulation-' . $simulation->id . '.csv', function ($handle) use ($simulation) {
            $genes = $simulation->geneIds();
            $trajectories = $simulation->trajectories();

            $header = [__('simulator.export.minutes')];
            foreach ($genes as $gene) {
                $header[] = $gene . ' ' . __('simulator.export.protein_mean');
                $header[] = $gene . ' ' . __('simulator.export.protein_sd');
                $header[] = $gene . ' ' . __('simulator.export.mrna_mean');
            }
            fputcsv($handle, $header);

            foreach ($simulation->time()['grid_minutes'] ?? [] as $index => $minute) {
                $row = [$minute];
                foreach ($genes as $gene) {
                    $row[] = $trajectories[$gene]['mean'][$index] ?? '';
                    $row[] = $trajectories[$gene]['sd'][$index] ?? '';
                    $row[] = $trajectories[$gene]['mrna_mean'][$index] ?? '';
                }
                fputcsv($handle, $row);
            }
        });
    }
}
