<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A compiled genetic circuit, stored for the same reasons an analysis is:
 * a refresh must not recompile, a design deserves a shareable URL, and changing
 * language must not discard the result.
 */
class Circuit extends Model
{
    use HasUuids;

    protected $fillable = ['source_text', 'language', 'expression', 'compiled', 'succeeded'];

    protected function casts(): array
    {
        return ['compiled' => 'array', 'succeeded' => 'boolean'];
    }

    public function specification(): array
    {
        return $this->compiled['specification'] ?? [];
    }

    public function gates(): array
    {
        return $this->compiled['gates'] ?? [];
    }

    public function units(): array
    {
        return $this->compiled['units'] ?? [];
    }

    public function parts(): array
    {
        return $this->compiled['parts'] ?? [];
    }

    public function totals(): array
    {
        return $this->compiled['totals'] ?? [];
    }

    public function fasta(): string
    {
        return $this->compiled['fasta'] ?? '';
    }

    /** @return array<int, array<string, mixed>> */
    public function diagnostics(?string $severity = null): array
    {
        $items = $this->compiled['diagnostics'] ?? [];

        return $severity === null
            ? $items
            : array_values(array_filter($items, fn ($item) => $item['severity'] === $severity));
    }

    public function diagnosticCounts(): array
    {
        return $this->compiled['diagnostic_counts'] ?? ['error' => 0, 'warning' => 0, 'info' => 0];
    }
}
