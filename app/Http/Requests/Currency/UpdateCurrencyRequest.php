<?php

namespace App\Http\Requests\Currency;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes',
                'string',
                'max:10',
                Rule::unique('currencies', 'code')->ignore($this->route('currency')),
            ],
            'name' => 'sometimes|string|max:255',
            'symbol' => 'sometimes|string|max:5',
            'decimals' => 'sometimes|integer|min:0|max:8',
            'is_base' => 'sometimes|boolean',
            'rate' => 'sometimes|numeric|gt:0',
        ];
    }
}
