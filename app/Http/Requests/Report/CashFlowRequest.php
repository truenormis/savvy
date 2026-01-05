<?php

namespace App\Http\Requests\Report;

use Illuminate\Validation\Rule;

class CashFlowRequest extends OverviewRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'group_by' => ['nullable', Rule::in(['day', 'week', 'month'])],
        ]);
    }
}
