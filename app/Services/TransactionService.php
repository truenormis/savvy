<?php

namespace App\Services;

use App\Builders\TransactionQueryBuilder;
use App\DTOs\TransactionData;
use App\DTOs\TransactionFilterData;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TransactionService
{

    public function getFiltered(TransactionFilterData $filters): LengthAwarePaginator
    {
        return TransactionQueryBuilder::make()
            ->withRelations()
            ->withItemsCount()
            ->applyFilters($filters)
            ->applySorting($filters)
            ->paginate($filters->perPage);
    }

    public function findOrFail(int $id): Transaction
    {
        return Transaction::with(['account.currency', 'toAccount.currency', 'category', 'items', 'tags'])
            ->findOrFail($id);
    }

    public function create(TransactionData $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $transactionData = $this->prepareTransactionData($data);
            $transaction = Transaction::create($transactionData);

            if ($data->hasItems()) {
                $this->createItems($transaction, $data->items);
            }

            if ($data->tagIds !== null) {
                $transaction->tags()->sync($data->tagIds);
            }

            return $transaction->load(['account.currency', 'toAccount.currency', 'category', 'items', 'tags']);
        });
    }

    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $transactionData = $this->prepareTransactionData($data);
            $transaction->update($transactionData);

            if ($data->items !== null) {
                $transaction->items()->delete();

                if ($data->hasItems()) {
                    $this->createItems($transaction, $data->items);
                }
            }

            if ($data->tagIds !== null) {
                $transaction->tags()->sync($data->tagIds);
            }

            return $transaction->load(['account.currency', 'toAccount.currency', 'category', 'items', 'tags']);
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction->items()->delete();
            $transaction->delete();
        });
    }

    public function duplicate(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            $newTransaction = $transaction->replicate(['created_at', 'updated_at']);
            $newTransaction->date = now()->toDateString();
            $newTransaction->save();

            foreach ($transaction->items as $item) {
                $newItem = $item->replicate(['created_at', 'updated_at']);
                $newItem->transaction_id = $newTransaction->id;
                $newItem->save();
            }

            $newTransaction->tags()->sync($transaction->tags->pluck('id'));

            return $newTransaction->load(['account.currency', 'toAccount.currency', 'category', 'items', 'tags']);
        });
    }

    public function getSummary(TransactionFilterData $filters): array
    {
        $transactions = TransactionQueryBuilder::make()
            ->applyFilters($filters)
            ->getQuery()
            ->with('account.currency')
            ->get();

        $baseCurrency = \App\Models\Currency::getBase();

        $income = 0.0;
        $expense = 0.0;

        foreach ($transactions as $transaction) {
            // Skip debt operations - they don't affect income/expense
            if ($transaction->type->isDebtOperation()) {
                continue;
            }

            $currency = $transaction->account->currency;
            $amountInBase = $currency->convertToBase((float) $transaction->amount);

            if ($transaction->type === TransactionType::Income) {
                $income += $amountInBase;
            } elseif ($transaction->type === TransactionType::Expense) {
                $expense += $amountInBase;
            }
            // Transfer doesn't affect total balance - money just moves between accounts
        }

        return [
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'balance' => round($income - $expense, 2),
            'transactions_count' => $transactions->count(),
            'currency' => $baseCurrency?->symbol ?? '$',
        ];
    }

    private function prepareTransactionData(TransactionData $data): array
    {
        $prepared = [
            'type' => $data->type,
            'account_id' => $data->accountId,
            'category_id' => $data->categoryId,
            'amount' => $data->amount,
            'description' => $data->description,
            'date' => $data->date,
        ];

        if ($data->type->isTransfer()) {
            $prepared['to_account_id'] = $data->toAccountId;
            $prepared['to_amount'] = $data->toAmount ?? $this->calculateToAmount($data);
            $prepared['exchange_rate'] = $data->exchangeRate ?? $this->calculateExchangeRate(
                $data->amount,
                $prepared['to_amount']
            );
            $prepared['category_id'] = null;
        }

        return $prepared;
    }

    private function calculateToAmount(TransactionData $data): float
    {
        $fromAccount = Account::with('currency')->find($data->accountId);
        $toAccount = Account::with('currency')->find($data->toAccountId);

        if ($fromAccount->currency_id === $toAccount->currency_id) {
            return $data->amount;
        }

        return $fromAccount->currency->convertTo($data->amount, $toAccount->currency);
    }

    private function calculateExchangeRate(float $amount, float $toAmount): ?float
    {
        if ($amount > 0) {
            return round($toAmount / $amount, 6);
        }

        return null;
    }

    private function createItems(Transaction $transaction, array $items): void
    {
        foreach ($items as $item) {
            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'name' => $item['name'],
                'quantity' => (int) $item['quantity'],
                'price_per_unit' => $item['price_per_unit'],
                'total_price' => (int) $item['quantity'] * $item['price_per_unit'],
            ]);
        }
    }
}
