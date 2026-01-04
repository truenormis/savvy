<?php

namespace App\Http\Requests\Budget;

use App\Enums\BudgetPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency_id' => 'nullable|exists:currencies,id',
            'period' => ['sometimes', Rule::enum(BudgetPeriod::class)],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_global' => 'boolean',
            'notify_at_percent' => 'nullable|integer|min:1|max:100',
            'is_active' => 'boolean',
            'category_ids' => 'array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'array',
            'tag_ids.*' => 'exists:tags,id',
        ];
    }
}
