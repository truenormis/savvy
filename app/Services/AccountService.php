<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

class AccountService
{
    public function getAll(bool $onlyActive = false): Collection
    {
        $query = Account::with('currency');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->get()->each(fn (Account $account) => $this->loadBalance($account));
    }

    public function findOrFail(int $id): Account
    {
        $account = Account::with('currency')->findOrFail($id);

        return $this->loadBalance($account);
    }

    public function create(array $data): Account
    {
        $account = Account::create($data);
        $account->load('currency');

        return $this->loadBalance($account);
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($data);
        $account->load('currency');

        return $this->loadBalance($account);
    }

    public function delete(Account $account): void
    {
        if ($account->transactions()->exists()) {
            throw new \DomainException('Cannot delete account that has transactions.');
        }

        $account->delete();
    }

    public function getBalanceHistory(string $startDate, string $endDate): array
    {
        $baseCurrency = Currency::getBase();
        if (!$baseCurrency) {
            return ['dates' => [], 'series' => [], 'currency' => '$'];
        }

        $accounts = Account::with('currency')->where('is_active', true)->get();
        $accountIds = $accounts->pluck('id')->toArray();

        // Calculate initial balance before start date for each account
        $runningBalances = [];
        foreach ($accounts as $account) {
            $runningBalances[$account->id] = $this->getAccountBalanceBeforeDate($account, $startDate);
        }

        // Get all transactions in the date range for active accounts
        $transactions = \App\Models\Transaction::whereBetween('date', [$startDate, $endDate])
            ->where(function ($query) use ($accountIds) {
                $query->whereIn('account_id', $accountIds)
                    ->orWhereIn('to_account_id', $accountIds);
            })
            ->orderBy('date')
            ->get();

        // Group transactions by date string
        $transactionsByDate = $transactions->groupBy(fn ($t) => $t->date->format('Y-m-d'));

        // Generate all dates in range
        $dates = [];
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        // Initialize series data for each account + total
        $seriesData = [];
        foreach ($accounts as $account) {
            $seriesData[$account->id] = [
                'name' => $account->name,
                'type' => $account->type,
                'data' => [],
            ];
        }
        $totalData = [];

        foreach ($dates as $date) {
            // Apply transactions for this date
            if (isset($transactionsByDate[$date])) {
                foreach ($transactionsByDate[$date] as $transaction) {
                    $accountId = $transaction->account_id;

                    switch ($transaction->type->value) {
                        case 'income':
                            if (isset($runningBalances[$accountId])) {
                                $runningBalances[$accountId] += (float) $transaction->amount;
                            }
                            break;
                        case 'expense':
                            if (isset($runningBalances[$accountId])) {
                                $runningBalances[$accountId] -= (float) $transaction->amount;
                            }
                            break;
                        case 'transfer':
                            if (isset($runningBalances[$accountId])) {
                                $runningBalances[$accountId] -= (float) $transaction->amount;
                            }
                            if ($transaction->to_account_id && isset($runningBalances[$transaction->to_account_id])) {
                                $runningBalances[$transaction->to_account_id] += (float) $transaction->to_amount;
                            }
                            break;
                    }
                }
            }

            // Calculate balance for each account in base currency
            $totalInBase = 0;
            foreach ($accounts as $account) {
                $balance = $runningBalances[$account->id] ?? 0;
                $balanceInBase = $account->currency_id === $baseCurrency->id
                    ? $balance
                    : $account->currency->convertTo($balance, $baseCurrency);

                $seriesData[$account->id]['data'][] = round($balanceInBase, 2);
                $totalInBase += $balanceInBase;
            }

            $totalData[] = round($totalInBase, 2);
        }

        // Build series array
        $series = [];
        foreach ($accounts as $account) {
            $series[] = $seriesData[$account->id];
        }
        // Add total series
        $series[] = [
            'name' => 'Total',
            'type' => 'total',
            'data' => $totalData,
        ];

        return [
            'dates' => $dates,
            'series' => $series,
            'currency' => $baseCurrency->symbol,
        ];
    }

    private function getAccountBalanceBeforeDate(Account $account, string $date): float
    {
        $income = $account->transactions()
            ->where('type', 'income')
            ->where('date', '<', $date)
            ->sum('amount');

        $expense = $account->transactions()
            ->where('type', 'expense')
            ->where('date', '<', $date)
            ->sum('amount');

        $transferOut = $account->transactions()
            ->where('type', 'transfer')
            ->where('date', '<', $date)
            ->sum('amount');

        $transferIn = \App\Models\Transaction::where('to_account_id', $account->id)
            ->where('date', '<', $date)
            ->sum('to_amount');

        return (float) $account->initial_balance + $income - $expense - $transferOut + $transferIn;
    }

    public function getSummary(?int $baseCurrencyId = null): array
    {
        $baseCurrency = $baseCurrencyId
            ? Currency::find($baseCurrencyId)
            : Currency::getBase();

        if (!$baseCurrency) {
            return [
                'total_balance' => 0,
                'base_currency_id' => null,
                'accounts_count' => 0,
            ];
        }

        $accounts = $this->getAll(onlyActive: true);
        $totalInBaseCurrency = 0;

        foreach ($accounts as $account) {
            $balance = $account->current_balance;

            if ($account->currency_id === $baseCurrency->id) {
                $totalInBaseCurrency += $balance;
            } else {
                $converted = $account->currency->convertTo($balance, $baseCurrency);
                $totalInBaseCurrency += $converted;
            }
        }

        return [
            'total_balance' => round($totalInBaseCurrency, 2),
            'currency' => $baseCurrency->symbol,
            'currency_code' => $baseCurrency->code,
            'accounts_count' => $accounts->count(),
        ];
    }

    private function loadBalance(Account $account): Account
    {
        $account->setAttribute('current_balance', $account->current_balance);

        return $account;
    }
}
