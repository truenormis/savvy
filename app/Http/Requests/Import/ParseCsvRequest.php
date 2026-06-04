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
            'upload_id' => ['required', 'string', 'uuid', 'exists:uploads,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'upload_id.required' => 'An uploaded file is required to start an import.',
            'upload_id.exists' => 'The uploaded file could not be found.',
        ];
    }
}
