<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One stochastic simulation run, stored for the reasons an analysis is — a
 * refresh must not re-run it, a result deserves a shareable URL, and changing
 * language must not discard it — plus one that is specific to this tool.
 *
 * Every run is random. Two runs of the same network with different seeds give
 * different numbers, so "the result I was looking at" is not something the user
 * can reconstruct by pressing the button again. Storing the run, seed included,
 * is what makes a figure in a report point at something real.
 */
class Simulation extends Model
{
    use HasUuids;

    /**
     * The networks the simulator can run.
     *
     * Duplicated from the backend so the form can be built without a round
     * trip, and validated there as well: the backend refuses an unknown preset
     * with a diagnostic rather than trusting this list to stay in step.
     */
    public const PRESETS = [
        'independent',
        'crosstalk_pair',
        'dual_reporter',
        'resource_competition',
        'toggle_switch',
    ];

    protected $fillable = ['preset', 'cells', 'minutes', 'seed', 'succeeded', 'result'];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'succeeded' => 'boolean',
            'cells' => 'integer',
            'minutes' => 'integer',
            'seed' => 'integer',
        ];
    }

    public function request(): array
    {
        return $this->result['request'] ?? [];
    }

    public function network(): array
    {
        return $this->result['network'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function genes(): array
    {
        return $this->network()['genes'] ?? [];
    }

    public function geneIds(): array
    {
        return array_column($this->genes(), 'id');
    }

    public function statistics(): array
    {
        return $this->result['statistics'] ?? [];
    }

    public function statisticsFor(string $gene): array
    {
        return $this->statistics()[$gene] ?? [];
    }

    public function trajectories(): array
    {
        return $this->result['trajectories'] ?? [];
    }

    public function distributions(): array
    {
        return $this->result['distributions'] ?? [];
    }

    public function crosstalk(): array
    {
        return $this->result['crosstalk'] ?? [];
    }

    public function attribution(): array
    {
        return $this->crosstalk()['attribution'] ?? [];
    }

    public function decomposition(): ?array
    {
        return $this->result['decomposition'] ?? null;
    }

    public function switching(): ?array
    {
        return $this->result['switching'] ?? null;
    }

    public function time(): array
    {
        return $this->result['time'] ?? [];
    }

    public function performance(): array
    {
        return $this->result['performance'] ?? [];
    }

    /**
     * The relative error on the noise figures, taken from the least certain
     * gene. Displayed next to them, because a coefficient of variation quoted
     * to four decimals from sixty cells is three decimals of theatre.
     */
    public function precision(): float
    {
        return (float) max(array_column($this->statistics(), 'precision') ?: [0]);
    }

    /** @return array<int, array<string, mixed>> */
    public function diagnostics(?string $severity = null): array
    {
        $items = $this->result['diagnostics'] ?? [];

        return $severity === null
            ? $items
            : array_values(array_filter($items, fn ($item) => $item['severity'] === $severity));
    }

    public function diagnosticCounts(): array
    {
        return $this->result['diagnostic_counts'] ?? ['error' => 0, 'warning' => 0, 'info' => 0];
    }

    /**
     * The headline: how much of the busiest gene's transcription was driven by
     * a signal meant for something else.
     */
    public function worstCrosstalk(): float
    {
        $shares = array_column($this->attribution(), 'crosstalk');

        return $shares === [] ? 0.0 : (float) max($shares);
    }
}
