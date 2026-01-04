<?php

namespace App\Http\Requests\Transaction;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTransactionRequest extends FormRequest
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
            'amount' => 'sometimes|numeric|gt:0',
            'to_amount' => 'nullable|numeric|gt:0',
            'exchange_rate' => 'nullable|numeric|gt:0',
            'description' => 'nullable|string|max:500',
            'date' => 'sometimes|date',
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
        $transaction = $this->route('transaction');
        $type = $this->input('type') ?? $transaction->type->value;

        if ($type === TransactionType::Transfer->value) {
            $toAccountId = $this->has('to_account_id')
                ? $this->input('to_account_id')
                : $transaction->to_account_id;

            if (!$toAccountId) {
                $validator->errors()->add('to_account_id', 'Destination account is required for transfers.');
            }
        }
    }

    private function validateCategoryType(Validator $validator): void
    {
        $transaction = $this->route('transaction');
        $type = $this->input('type') ?? $transaction->type->value;
        $categoryId = $this->has('category_id')
            ? $this->input('category_id')
            : $transaction->category_id;

        if ($type === TransactionType::Transfer->value && $categoryId) {
            $validator->errors()->add('category_id', 'Category should not be set for transfers.');
            return;
        }

        if ($type !== TransactionType::Transfer->value && $categoryId) {
            $category = Category::find($categoryId);
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

        $amount = $this->input('amount') ?? $this->route('transaction')->amount;

        if (abs($itemsTotal - $amount) > 0.01) {
            $validator->errors()->add('items', "Items total ({$itemsTotal}) must equal transaction amount ({$amount}).");
        }
    }

    private function validateSufficientFunds(Validator $validator): void
    {
        $transaction = $this->route('transaction');
        $originalType = $transaction->type->value;
        $originalAmount = (float) $transaction->amount;
        $originalAccountId = $transaction->account_id;

        $newType = $this->input('type') ?? $originalType;
        $newAmount = (float) ($this->input('amount') ?? $originalAmount);
        $newAccountId = $this->input('account_id') ?? $originalAccountId;

        // Only check for expense and transfer
        if (!in_array($newType, [TransactionType::Expense->value, TransactionType::Transfer->value])) {
            return;
        }

        $account = \App\Models\Account::find($newAccountId);
        if (!$account) {
            return;
        }

        // Calculate available balance
        // Current balance already reflects the original transaction
        $currentBalance = $account->current_balance;

        // If same account, add back the original amount (if it was expense/transfer)
        if ($newAccountId == $originalAccountId) {
            if (in_array($originalType, [TransactionType::Expense->value, TransactionType::Transfer->value])) {
                $currentBalance += $originalAmount;
            } elseif ($originalType === TransactionType::Income->value) {
                $currentBalance -= $originalAmount;
            }
        }

        if ($currentBalance < $newAmount) {
            $validator->errors()->add('amount', 'Insufficient funds. Available balance: ' . number_format($currentBalance, 2));
        }
    }
}
