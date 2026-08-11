<?php

namespace App\Http\Requests;

use App\Models\MemoryDesign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signal' => ['required', 'string', Rule::in(MemoryDesign::SIGNALS)],
            'chassis' => ['required', 'string', Rule::in(MemoryDesign::CHASSIS)],
            'recombinase' => ['nullable', 'string', Rule::in(MemoryDesign::RECOMBINASES)],
            'hold_hours' => ['required', 'numeric', 'min:0.5', 'max:168'],
            'signal_minutes' => ['required', 'numeric', 'min:1', 'max:720'],
            'strength' => ['required', 'numeric', 'min:0.1', 'max:1'],
            'must_be_reversible' => ['nullable', 'boolean'],
            'on_plasmid' => ['nullable', 'boolean'],
            // The cargo the register inverts. Optional: without one the tool
            // uses a constitutive promoter, which makes the stored bit
            // directly readable and is what most designs want anyway.
            'payload' => ['nullable', 'string', 'max:60000'],
        ];
    }

    public function messages(): array
    {
        return [
            'signal.required' => __('errors.memory.signal_required'),
            'signal.in' => __('errors.memory.signal_unknown'),
            'chassis.in' => __('errors.memory.chassis_unknown'),
            'hold_hours.min' => __('errors.memory.hold_range', ['minimum' => 0.5, 'maximum' => 168]),
            'hold_hours.max' => __('errors.memory.hold_range', ['minimum' => 0.5, 'maximum' => 168]),
            'payload.max' => __('errors.memory.payload_too_long'),
        ];
    }

    public function attributes(): array
    {
        return [
            'signal' => __('memory.form.signal'),
            'chassis' => __('memory.form.chassis'),
            'hold_hours' => __('memory.form.hold'),
            'signal_minutes' => __('memory.form.exposure'),
            'strength' => __('memory.form.strength'),
            'payload' => __('memory.form.payload'),
        ];
    }

    public function parameters(): array
    {
        return [
            'signal' => $this->string('signal')->toString(),
            'chassis' => $this->string('chassis')->toString(),
            'recombinase' => $this->filled('recombinase')
                ? $this->string('recombinase')->toString()
                : 'bxb1',
            'hold_hours' => (float) $this->input('hold_hours'),
            'signal_minutes' => (float) $this->input('signal_minutes'),
            'strength' => (float) $this->input('strength'),
            'must_be_reversible' => $this->boolean('must_be_reversible'),
            'on_plasmid' => $this->boolean('on_plasmid'),
            'payload' => $this->string('payload')->toString(),
        ];
    }
}
