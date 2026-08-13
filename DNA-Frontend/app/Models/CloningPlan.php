<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One cloning plan: where a template cuts, and what would amplify it.
 *
 * Stored for the reason the other tools store their output — a result should
 * survive a refresh and have a URL — and for one specific to this tab. A primer
 * pair is ordered from a supplier days after it is designed, and the order form
 * asks for a sequence with no room for the reasoning behind it. Keeping the plan
 * means the warning that the chosen enzyme also cuts the insert is still there
 * when the oligos arrive and nothing works.
 *
 * @property string $id
 * @property string|null $label
 * @property int $template_length
 * @property string $panel
 * @property bool $circular
 * @property string|null $forward_enzyme
 * @property string|null $reverse_enzyme
 * @property bool $succeeded
 * @property array<string, mixed> $result
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CloningPlan extends Model
{
    use HasUuids;

    /** Mirrored from the backend, which validates them again and refuses the rest. */
    public const PANELS = ['teaching', 'golden_gate', 'commercial'];

    protected $fillable = [
        'label', 'template_length', 'panel', 'circular',
        'forward_enzyme', 'reverse_enzyme', 'succeeded', 'result',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'succeeded' => 'boolean',
            'circular' => 'boolean',
            'template_length' => 'integer',
        ];
    }

    /** @return array<string, mixed> */
    public function digest(): array
    {
        return $this->result['digest'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function enzymes(): array
    {
        return $this->digest()['enzymes'] ?? [];
    }

    /**
     * The enzymes that cut exactly once.
     *
     * The single most consulted number in the whole result: an enzyme that cuts
     * twice cannot open a vector without also removing a piece of it.
     *
     * @return array<int, string>
     */
    public function uniqueCutters(): array
    {
        return $this->digest()['unique_cutters'] ?? [];
    }

    /** @return array<int, string> */
    public function nonCutters(): array
    {
        return $this->digest()['non_cutters'] ?? [];
    }

    /** @return array<string, mixed> */
    public function primers(): array
    {
        return $this->result['primers'] ?? [];
    }

    /** @return array<string, mixed> */
    public function primer(string $direction): array
    {
        return $this->primers()[$direction] ?? [];
    }

    /** @return array<string, mixed> */
    public function amplicon(): array
    {
        return $this->primers()['amplicon'] ?? [];
    }

    /** @return array<string, mixed> */
    public function conditions(): array
    {
        return $this->primers()['conditions'] ?? [];
    }

    /** @return array<string, mixed> */
    public function criteria(): array
    {
        return $this->primers()['criteria'] ?? [];
    }

    /** @return array<string, mixed> */
    public function tails(): array
    {
        return $this->result['tails'] ?? [];
    }

    /** @return array<string, mixed> */
    public function tail(string $direction): array
    {
        return $this->tails()['ends'][$direction] ?? [];
    }

    public function hasTails(): bool
    {
        return $this->tails() !== [];
    }

    /** @return array<int, array<string, mixed>> */
    public function diagnostics(): array
    {
        return $this->result['diagnostics'] ?? [];
    }

    /** @return array<string, int> */
    public function diagnosticCounts(): array
    {
        return $this->result['diagnostic_counts'] ?? [];
    }

    /**
     * The primers as an oligo order: what gets pasted into a supplier's form.
     *
     * The tailed sequence when there is one, because that is what must be
     * synthesised — ordering the binding region alone is the mistake this
     * method exists to prevent.
     *
     * @return array<int, array{name: string, sequence: string, length: int}>
     */
    public function order(): array
    {
        $rows = [];

        foreach (['forward', 'reverse'] as $direction) {
            $tail = $this->tail($direction);
            $primer = $this->primer($direction);
            $sequence = $tail['sequence'] ?? ($primer['sequence'] ?? '');

            if ($sequence === '') {
                continue;
            }

            $rows[] = [
                'name' => substr($this->id, 0, 8) . '_' . $direction,
                'sequence' => $sequence,
                'length' => strlen($sequence),
            ];
        }

        return $rows;
    }
}
