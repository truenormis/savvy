<?php

namespace App\Http\Requests\Budget;

use App\Enums\BudgetPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency_id' => 'nullable|exists:currencies,id',
            'period' => ['required', Rule::enum(BudgetPeriod::class)],
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
