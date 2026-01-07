<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class ParseCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:5120', // 5MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a CSV file to upload.',
            'file.file' => 'The uploaded file is invalid.',
            'file.mimes' => 'The file must be a CSV file.',
            'file.max' => 'The file size must not exceed 5MB.',
        ];
    }
}
