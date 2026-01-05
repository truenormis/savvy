<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_type' => ['required', Rule::in(['month', 'quarter', 'year', 'ytd', 'custom'])],
            'period_value' => 'nullable|string',
            'start_date' => 'nullable|date|required_if:period_type,custom',
            'end_date' => 'nullable|date|after_or_equal:start_date|required_if:period_type,custom',
            'compare_with' => ['nullable', Rule::in(['none', 'previous_period', 'same_period_last_year'])],
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'exists:accounts,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'merchant_search' => 'nullable|string|max:100',
        ];
    }
}
