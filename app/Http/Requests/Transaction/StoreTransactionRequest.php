<?php

namespace App\Http\Requests\Transaction;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'nullable|exists:accounts,id|different:account_id',
            'category_id' => 'nullable|exists:categories,id',
            'amount' => 'required|numeric|gt:0',
            'to_amount' => 'nullable|numeric|gt:0',
            'exchange_rate' => 'nullable|numeric|gt:0',
            'description' => 'nullable|string|max:500',
            'date' => 'required|date',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.price_per_unit' => 'required_with:items|numeric|gte:0',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->validateTransferFields($validator);
                $this->validateCategoryType($validator);
                $this->validateItemsTotal($validator);
                $this->validateSufficientFunds($validator);
            }
        ];
    }

    private function validateTransferFields(Validator $validator): void
    {
        $type = $this->input('type');

        if ($type === TransactionType::Transfer->value) {
            if (!$this->input('to_account_id')) {
                $validator->errors()->add('to_account_id', 'Destination account is required for transfers.');
            }
        }
    }

    private function validateCategoryType(Validator $validator): void
    {
        $type = $this->input('type');
        $categoryId = $this->input('category_id');

        if ($type === TransactionType::Transfer->value && $categoryId) {
            $validator->errors()->add('category_id', 'Category should not be set for transfers.');
            return;
        }

        if ($type !== TransactionType::Transfer->value && $categoryId) {
            $category = \App\Models\Category::find($categoryId);
            if ($category && $category->type !== $type) {
                $validator->errors()->add('category_id', 'Category type must match transaction type.');
            }
        }
    }

    private function validateItemsTotal(Validator $validator): void
    {
        $items = $this->input('items', []);

        if (empty($items)) {
            return;
        }

        $itemsTotal = collect($items)->sum(function ($item) {
            return ($item['quantity'] ?? 0) * ($item['price_per_unit'] ?? 0);
        });

        $amount = $this->input('amount');

        if (abs($itemsTotal - $amount) > 0.01) {
            $validator->errors()->add('items', "Items total ({$itemsTotal}) must equal transaction amount ({$amount}).");
        }
    }

    private function validateSufficientFunds(Validator $validator): void
    {
        $type = $this->input('type');

        // Only check for expense and transfer
        if (!in_array($type, [TransactionType::Expense->value, TransactionType::Transfer->value])) {
            return;
        }

        $accountId = $this->input('account_id');
        $amount = (float) $this->input('amount');

        $account = \App\Models\Account::find($accountId);
        if (!$account) {
            return;
        }

        if ($account->current_balance < $amount) {
            $validator->errors()->add('amount', 'Insufficient funds. Available balance: ' . number_format($account->current_balance, 2));
        }
    }
}
