<?php

namespace App\Http\Requests\Currency;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:10|unique:currencies,code',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:5',
            'decimals' => 'sometimes|integer|min:0|max:8',
            'is_base' => 'sometimes|boolean',
            'rate' => 'sometimes|numeric|gt:0',
        ];
    }
}
