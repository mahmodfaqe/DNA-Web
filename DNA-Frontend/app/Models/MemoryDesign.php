<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One memory-circuit design: the comparison, the verdict, and the DNA.
 *
 * Stored for the reasons the other three tools store their output, and one
 * more. The recommendation depends on parameters the reader will want to argue
 * with — how long the memory must hold, how leaky the sensor is, whether the
 * construct sits on a plasmid — so a design is only useful if the inputs that
 * produced it travel with it.
 */
class MemoryDesign extends Model
{
    use HasUuids;

    /** Mirrored from the backend, which validates them again and refuses the rest. */
    public const SIGNALS = [
        'lactose', 'arabinose', 'tetracycline', 'temperature', 'oxygen', 'quorum', 'ph_acid',
    ];

    public const CHASSIS = ['ecoli', 'bsubtilis', 'yeast'];

    public const RECOMBINASES = ['bxb1', 'phic31'];

    protected $fillable = [
        'signal', 'chassis', 'architecture', 'hold_hours', 'succeeded', 'result',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'succeeded' => 'boolean',
            'hold_hours' => 'integer',
        ];
    }

    public function request(): array
    {
        return $this->result['request'] ?? [];
    }

    public function recommendation(): array
    {
        return $this->result['recommendation'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function comparison(): array
    {
        return $this->result['comparison'] ?? [];
    }

    public function outcomes(): array
    {
        return $this->result['outcomes'] ?? [];
    }

    public function outcomeFor(string $architecture): array
    {
        return $this->outcomes()[$architecture] ?? [];
    }

    public function orientation(): array
    {
        return $this->result['orientation'] ?? [];
    }

    public function composition(): array
    {
        return $this->result['composition'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function constructs(): array
    {
        return $this->result['constructs'] ?? [];
    }

    public function parts(): array
    {
        return $this->result['parts'] ?? [];
    }

    public function totals(): array
    {
        return $this->result['totals'] ?? [];
    }

    public function synthesis(): array
    {
        return $this->result['synthesis'] ?? [];
    }

    public function fasta(): string
    {
        return $this->result['fasta'] ?? '';
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
     * Whether the two architectures finished close enough together that the
     * verdict should be read as a preference rather than a conclusion.
     */
    public function isCloseCall(): bool
    {
        $gap = $this->recommendation()['gap'] ?? null;

        return $gap !== null && $gap < 0.12;
    }
}
