<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The accepted formats are decided by the file name, not by its detected
     * MIME type. `.fasta`, `.fa` and `.fna` have no registered MIME type, so a
     * content sniff reports them as plain text at best and as
     * application/octet-stream at worst — `mimes:` compares the *guessed*
     * extension and rejects the very files this form exists to accept. The
     * sequence itself is validated by the backend parser, which is the only
     * thing that can tell a real FASTA record from a text file.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fasta_file' => [
                'required',
                'file',
                'extensions:fasta,fa,fna,txt',
                'max:' . (int) config('services.backend.max_upload_kb', 10240),
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
        ];
    }

    public function attributes(): array
    {
        return ['fasta_file' => __('errors.validation.attribute')];
    }
}
