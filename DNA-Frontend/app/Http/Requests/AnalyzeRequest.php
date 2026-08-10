<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class AnalyzeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fasta_file' => [
                'required',
                File::types(['fasta', 'fa', 'fna', 'txt'])
                    ->max((int) config('services.backend.max_upload_kb', 10240)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'fasta_file.required' => __('errors.validation.required'),
            'fasta_file.max' => __('errors.validation.max', [
                'megabytes' => round(((int) config('services.backend.max_upload_kb', 10240)) / 1024, 1),
            ]),
            'fasta_file.extensions' => __('errors.validation.extensions'),
            'fasta_file.mimes' => __('errors.validation.extensions'),
            'fasta_file.mimetypes' => __('errors.validation.extensions'),
        ];
    }

    public function attributes(): array
    {
        return ['fasta_file' => __('errors.validation.attribute')];
    }
}
