<?php

namespace App\Http\Requests;

use App\Enums\ReportCompareWith;
use App\Enums\ReportGroupBy;
use App\Enums\ReportMetric;
use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // What to show
            'type' => ['required', Rule::enum(ReportType::class)],

            // Period
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',

            // Grouping
            'group_by' => ['nullable', Rule::enum(ReportGroupBy::class)],
            'sub_group_by' => ['nullable', Rule::enum(ReportGroupBy::class)],

            // Filters
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'exists:accounts,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',

            // Metrics
            'metrics' => 'nullable|array',
            'metrics.*' => [Rule::enum(ReportMetric::class)],

            // Comparison
            'compare_with' => ['nullable', Rule::enum(ReportCompareWith::class)],
        ];
    }
}
