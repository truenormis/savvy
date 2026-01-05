<?php

namespace App\Http\Requests\Debt;

use App\Enums\DebtType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'debt_type' => ['required', Rule::enum(DebtType::class)],
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|gt:0',
            'due_date' => 'nullable|date',
            'counterparty' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'Debt amount must be greater than zero.',
        ];
    }
}
