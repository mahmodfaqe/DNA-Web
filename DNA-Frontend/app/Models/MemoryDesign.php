<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One memory-circuit design: the comparison, the verdict, and the DNA.
 *
 * Stored for the reasons the other three tools store their output, and one
 * more. The recommendation depends on parameters the reader will want to argue
 * with — how long the memory must hold, how leaky the sensor is, whether the
 * construct sits on a plasmid — so a design is only useful if the inputs that
 * produced it travel with it.
 *
 * @property string $id
 * @property string $signal
 * @property string $chassis
 * @property string|null $architecture
 * @property int $hold_hours
 * @property bool $succeeded
 * @property array<string, mixed> $result
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MemoryDesign extends Model
{
    use HasUuids;

    /** Mirrored from the backend, which validates them again and refuses the rest. */
    public const SIGNALS = [
        // Bacterial sensors.
        'lactose', 'arabinose', 'tetracycline', 'temperature', 'oxygen', 'quorum', 'ph_acid',
        // Yeast sensors. A promoter read by a sigma factor is not read in a
        // nucleus, so a eukaryotic host has its own list and the backend
        // refuses any pairing that crosses the two.
        'galactose', 'copper', 'estradiol',
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

    /** @return array<string, mixed> */
    public function request(): array
    {
        return $this->result['request'] ?? [];
    }

    /** @return array<string, mixed> */
    public function recommendation(): array
    {
        return $this->result['recommendation'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function comparison(): array
    {
        return $this->result['comparison'] ?? [];
    }

    /** @return array<string, array<string, mixed>> */
    public function outcomes(): array
    {
        return $this->result['outcomes'] ?? [];
    }

    /** @return array<string, mixed> */
    public function outcomeFor(string $architecture): array
    {
        return $this->outcomes()[$architecture] ?? [];
    }

    /** @return array<string, mixed> */
    public function orientation(): array
    {
        return $this->result['orientation'] ?? [];
    }

    /** @return array<string, mixed> */
    public function composition(): array
    {
        return $this->result['composition'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function constructs(): array
    {
        return $this->result['constructs'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function parts(): array
    {
        return $this->result['parts'] ?? [];
    }

    /** @return array<string, mixed> */
    public function totals(): array
    {
        return $this->result['totals'] ?? [];
    }

    /** @return array<string, mixed> */
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

    /** @return array<string, int> */
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
