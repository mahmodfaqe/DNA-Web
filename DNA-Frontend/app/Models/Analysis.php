<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A completed analysis, stored so that:
 *   - a browser refresh does not re-upload and re-run the whole job;
 *   - a result has a URL that can be pasted into a message or a paper;
 *   - switching language keeps the results on screen instead of discarding them.
 */
class Analysis extends Model
{
    use HasUuids;

    protected $fillable = [
        'filename',
        'size_bytes',
        'checksum',
        'gene_count',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'size_bytes' => 'integer',
            'gene_count' => 'integer',
        ];
    }

    public function summary(): array
    {
        return $this->payload['summary'] ?? [];
    }

    public function genes(): array
    {
        return $this->payload['genes'] ?? [];
    }

    public function comparisons(): array
    {
        return $this->payload['comparisons'] ?? [];
    }

    public function variantCount(): int
    {
        return array_sum(array_column($this->comparisons(), 'total_variants'));
    }
}
