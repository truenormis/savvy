<?php

namespace App\Http\Requests\Recurring;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'account_id' => 'sometimes|exists:accounts,id',
            'to_account_id' => 'nullable|exists:accounts,id|different:account_id',
            'category_id' => 'nullable|exists:categories,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'to_amount' => 'nullable|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'frequency' => ['sometimes', Rule::enum(RecurringFrequency::class)],
            'interval' => 'sometimes|integer|min:1|max:365',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'tag_ids' => 'array',
            'tag_ids.*' => 'exists:tags,id',
        ];
    }
}
