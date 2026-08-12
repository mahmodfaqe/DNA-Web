<?php

namespace App\Http\Requests;

use App\Models\Simulation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The bounds here are deliberately the same as the backend's, and the backend
 * still checks them.
 *
 * That duplication is on purpose. Validating in the form gives the user a
 * message in their own language before anything is queued; validating in the
 * backend means the limits hold for anyone calling the API directly. Neither
 * one is load-bearing on its own.
 */
class SimulateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'preset' => ['required', 'string', Rule::in(Simulation::PRESETS)],
            'cells' => ['required', 'integer', 'min:4', 'max:200'],
            'minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'induction' => ['required', 'numeric', 'min:0', 'max:1'],
            'crosstalk' => ['required', 'numeric', 'min:0', 'max:1'],
            'variability' => ['required', 'numeric', 'min:0', 'max:0.6'],
            'resource_coupling' => ['nullable', 'boolean'],
            // Blank means "pick one and tell me what it was", which is what
            // most people want; a value means "reproduce that run exactly".
            'seed' => ['nullable', 'integer', 'min:0', 'max:2147483646'],
        ];
    }

    public function messages(): array
    {
        return [
            'preset.required' => __('errors.simulator.preset_required'),
            'preset.in' => __('errors.simulator.preset_unknown'),
            'cells.min' => __('errors.simulator.cells_range', ['minimum' => 4, 'maximum' => 200]),
            'cells.max' => __('errors.simulator.cells_range', ['minimum' => 4, 'maximum' => 200]),
            'minutes.min' => __('errors.simulator.minutes_range', ['minimum' => 5, 'maximum' => 240]),
            'minutes.max' => __('errors.simulator.minutes_range', ['minimum' => 5, 'maximum' => 240]),
            'seed.integer' => __('errors.simulator.seed_invalid'),
        ];
    }

    public function attributes(): array
    {
        return [
            'preset' => __('simulator.form.network'),
            'cells' => __('simulator.form.cells'),
            'minutes' => __('simulator.form.duration'),
            'induction' => __('simulator.form.induction'),
            'crosstalk' => __('simulator.form.crosstalk'),
            'variability' => __('simulator.form.variability'),
            'seed' => __('simulator.form.seed'),
        ];
    }

    /**
     * The payload the analysis service expects.
     *
     * An unchecked checkbox is absent from the request body rather than false,
     * so resource coupling is read through `boolean()` — which treats "missing"
     * as off instead of letting it fall through as null and be read as the
     * backend's default of on.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'preset' => $this->string('preset')->toString(),
            'cells' => $this->integer('cells'),
            'minutes' => $this->integer('minutes'),
            'induction' => (float) $this->input('induction'),
            'crosstalk' => (float) $this->input('crosstalk'),
            'variability' => (float) $this->input('variability'),
            'resource_coupling' => $this->boolean('resource_coupling'),
            'seed' => $this->filled('seed') ? $this->integer('seed') : null,
        ];
    }
}
