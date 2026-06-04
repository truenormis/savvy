<?php

namespace App\Http\Requests\Report;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverviewRequest extends FormRequest
{
    private const MAX_CUSTOM_RANGE_DAYS = 1830;

    private const PERIOD_VALUE_FORMATS = [
        'month' => '/^\d{4}-(0[1-9]|1[0-2])$/',
        'quarter' => '/^\d{4}-Q[1-4]$/',
        'year' => '/^\d{4}$/',
    ];

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $periodType = $this->input('period_type');
            $periodValue = $this->input('period_value');

            if ($periodValue !== null && isset(self::PERIOD_VALUE_FORMATS[$periodType])
                && ! preg_match(self::PERIOD_VALUE_FORMATS[$periodType], $periodValue)) {
                $validator->errors()->add('period_value', "Invalid period_value format for period_type \"{$periodType}\".");
            }

            if ($periodType === 'custom' && $this->filled('start_date') && $this->filled('end_date')) {
                $span = Carbon::parse($this->input('start_date'))->diffInDays(Carbon::parse($this->input('end_date')));
                if ($span > self::MAX_CUSTOM_RANGE_DAYS) {
                    $validator->errors()->add('end_date', 'Custom range cannot exceed '.self::MAX_CUSTOM_RANGE_DAYS.' days.');
                }
            }
        });
    }
}
