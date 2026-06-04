<?php

namespace App\Http\Requests\Upload;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.PartNumber' => ['required', 'integer', 'min:1'],
            'parts.*.ETag' => ['nullable', 'string', 'max:255'],
        ];
    }
}
