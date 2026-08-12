<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:8', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => __('errors.compiler.required'),
            'description.min' => __('errors.compiler.too_short'),
            'description.max' => __('errors.compiler.too_long', ['characters' => 2000]),
        ];
    }

    public function attributes(): array
    {
        return ['description' => __('errors.compiler.attribute')];
    }
}
