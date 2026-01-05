<?php

namespace App\Http\Requests\Report;

use Illuminate\Validation\Rule;

class TransactionReportRequest extends OverviewRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'group_by' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
    }
}
