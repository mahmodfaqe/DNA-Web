<?php

namespace App\Http\Requests;

use App\Models\CloningPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Accepted as pasted text rather than as an upload: the sequence
            // being cloned is usually a region copied out of a map, not a file.
            'sequence' => ['required', 'string', 'min:30', 'max:60000'],
            'label' => ['nullable', 'string', 'max:120'],
            'panel' => ['required', 'string', Rule::in(CloningPlan::PANELS)],
            'circular' => ['nullable', 'boolean'],

            'design_primers' => ['nullable', 'boolean'],
            'target_start' => ['nullable', 'integer', 'min:1'],
            'target_end' => ['nullable', 'integer', 'min:1'],
            'target_tm' => ['nullable', 'numeric', 'min:45', 'max:75'],
            'min_length' => ['nullable', 'integer', 'min:15', 'max:40'],
            'max_length' => ['nullable', 'integer', 'min:15', 'max:45'],

            // Enzyme names are checked by the backend against REBASE rather than
            // against a list mirrored here, which would go stale silently.
            'forward_enzyme' => ['nullable', 'string', 'max:20'],
            'reverse_enzyme' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator(mixed $validator): void
    {
        $validator->after(function ($validator): void {
            $start = $this->integer('target_start');
            $end = $this->integer('target_end');

            if ($start > 0 && $end > 0 && $start >= $end) {
                $validator->errors()->add('target_end', __('errors.cloning.target_order'));
            }

            if ($this->filled('min_length') && $this->filled('max_length')
                && $this->integer('min_length') > $this->integer('max_length')) {
                $validator->errors()->add('max_length', __('errors.cloning.length_order'));
            }
        });
    }

    /** @return array<string, mixed> */
    public function messages(): array
    {
        return [
            'sequence.required' => __('errors.cloning.sequence_required'),
            'sequence.min' => __('errors.cloning.sequence_too_short', ['minimum' => 30]),
            'sequence.max' => __('errors.cloning.sequence_too_long', ['maximum' => 60000]),
            'panel.in' => __('errors.cloning.panel_unknown'),
            'target_tm.min' => __('errors.cloning.tm_range', ['minimum' => 45, 'maximum' => 75]),
            'target_tm.max' => __('errors.cloning.tm_range', ['minimum' => 45, 'maximum' => 75]),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'sequence' => __('cloning.form.sequence'),
            'label' => __('cloning.form.label'),
            'panel' => __('cloning.form.panel'),
            'target_start' => __('cloning.form.target_start'),
            'target_end' => __('cloning.form.target_end'),
            'target_tm' => __('cloning.form.target_tm'),
            'min_length' => __('cloning.form.min_length'),
            'max_length' => __('cloning.form.max_length'),
            'forward_enzyme' => __('cloning.form.forward_enzyme'),
            'reverse_enzyme' => __('cloning.form.reverse_enzyme'),
        ];
    }

    /**
     * The request as the backend expects it.
     *
     * Whitespace and FASTA header lines are stripped here rather than in the
     * backend, because a reader pasting from a genome browser will paste both
     * and being told "invalid character: >" is not help.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        $parameters = [
            'sequence' => $this->cleanSequence(),
            'panel' => $this->string('panel')->toString(),
            'circular' => $this->boolean('circular'),
            'design_primers' => $this->boolean('design_primers'),
        ];

        if ($parameters['design_primers']) {
            if ($this->filled('target_start') || $this->filled('target_end')) {
                $parameters['target'] = array_filter([
                    'start' => $this->integer('target_start') ?: null,
                    'end' => $this->integer('target_end') ?: null,
                ]);
            }

            foreach (['target_tm', 'min_length', 'max_length'] as $key) {
                if ($this->filled($key)) {
                    $parameters[$key] = $this->input($key) + 0;
                }
            }
        }

        $tails = array_filter([
            'forward_enzyme' => trim($this->string('forward_enzyme')->toString()),
            'reverse_enzyme' => trim($this->string('reverse_enzyme')->toString()),
        ]);

        if ($tails !== [] && $parameters['design_primers']) {
            $parameters['tails'] = $tails;
        }

        return $parameters;
    }

    public function cleanSequence(): string
    {
        $lines = preg_split('/\R/', $this->string('sequence')->toString()) ?: [];
        $kept = array_filter($lines, fn (string $line) => ! str_starts_with(trim($line), '>'));

        return strtoupper((string) preg_replace('/\s+/', '', implode('', $kept)));
    }
}
