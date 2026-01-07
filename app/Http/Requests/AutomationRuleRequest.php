<?php

namespace App\Http\Requests;

use App\Enums\TriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normalize conditions: convert 'operator' to 'op'
        if ($this->has('conditions.conditions')) {
            $conditions = $this->input('conditions.conditions');
            $normalized = collect($conditions)->map(function ($condition) {
                if (isset($condition['operator']) && !isset($condition['op'])) {
                    $condition['op'] = $condition['operator'];
                    unset($condition['operator']);
                }
                return $condition;
            })->all();

            $this->merge([
                'conditions' => [
                    'match' => $this->input('conditions.match'),
                    'conditions' => $normalized,
                ],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['required', Rule::enum(TriggerType::class)],
            'priority' => ['required', 'integer', 'min:1', 'max:100'],
            'conditions' => ['required', 'array'],
            'conditions.match' => ['required', 'in:all,any'],
            'conditions.conditions' => ['required', 'array'],
            'conditions.conditions.*.field' => ['required', 'string'],
            'conditions.conditions.*.op' => ['required', 'string'],
            'conditions.conditions.*.value' => ['present'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.category_id' => ['nullable', 'integer'],
            'actions.*.tag_ids' => ['nullable', 'array'],
            'actions.*.tag_ids.*' => ['integer'],
            'actions.*.value' => ['nullable', 'string'],
            'actions.*.template' => ['nullable', 'string'],
            'actions.*.description' => ['nullable', 'string'],
            'actions.*.to_account_id' => ['nullable', 'integer'],
            'actions.*.from_account_id' => ['nullable'],
            'actions.*.amount_formula' => ['nullable', 'string'],
            'actions.*.amount' => ['nullable'],
            'is_active' => ['boolean'],
            'stop_processing' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'conditions.required' => 'At least one condition is required.',
            'conditions.conditions.required' => 'At least one condition is required.',
            'actions.required' => 'At least one action is required.',
            'actions.min' => 'At least one action is required.',
        ];
    }
}
