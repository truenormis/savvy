<?php

namespace App\Http\Requests\Upload;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bucket' => ['required', 'string', Rule::in(array_keys(config('uploads.buckets')))],
            'filename' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
