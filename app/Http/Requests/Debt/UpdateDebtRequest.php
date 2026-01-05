<?php

namespace App\Http\Requests\Debt;

use App\Enums\DebtType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'debt_type' => ['sometimes', Rule::enum(DebtType::class)],
            'currency_id' => 'sometimes|exists:currencies,id',
            'amount' => 'sometimes|numeric|gt:0',
            'due_date' => 'nullable|date',
            'counterparty' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }
}
