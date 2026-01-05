<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

class AccountService
{
    public function getAll(bool $onlyActive = false, bool $excludeDebts = false): Collection
    {
        $query = Account::with('currency');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        if ($excludeDebts) {
            $query->regularAccounts();
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

        $accounts = Account::with('currency')
            ->where('is_active', true)
            ->regularAccounts()
            ->get();
        $accountIds = $accounts->pluck('id')->toArray();

        // Calculate initial balance before start date for each account
        $runningBalances = [];
        foreach ($accounts as $account) {
            $runningBalances[$account->id] = $this->getAccountBalanceBeforeDate($account, $startDate);
        }

        // Get all transactions in the date range for active accounts
        // Use Carbon dates to handle datetime fields properly
        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();

        $transactions = \App\Models\Transaction::whereBetween('date', [$start, $end])
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
                        case 'debt_collection': // Money comes to regular account
                            if (isset($runningBalances[$accountId])) {
                                $runningBalances[$accountId] += (float) $transaction->amount;
                            }
                            break;
                        case 'expense':
                        case 'debt_payment': // Money goes from regular account
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
        // Income + debt collections (money coming in)
        $income = $account->transactions()
            ->whereIn('type', ['income', 'debt_collection'])
            ->where('date', '<', $date)
            ->sum('amount');

        // Expenses + debt payments (money going out)
        $expense = $account->transactions()
            ->whereIn('type', ['expense', 'debt_payment'])
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
                'debts_impact' => 0,
                'net_worth' => 0,
                'base_currency_id' => null,
                'accounts_count' => 0,
                'debts_count' => 0,
            ];
        }

        // Regular accounts (bank, crypto, cash)
        $regularAccounts = $this->getAll(onlyActive: true, excludeDebts: true);
        $totalRegularBalance = 0;

        foreach ($regularAccounts as $account) {
            $balance = $account->current_balance;

            if ($account->currency_id === $baseCurrency->id) {
                $totalRegularBalance += $balance;
            } else {
                $totalRegularBalance += $account->currency->convertTo($balance, $baseCurrency);
            }
        }

        // Debts
        $debts = Account::with('currency')
            ->where('type', 'debt')
            ->where('is_active', true)
            ->where('is_paid_off', false)
            ->get();

        $debtsImpact = 0;
        foreach ($debts as $debt) {
            $remainingDebt = $debt->current_balance;
            $remainingInBase = $debt->currency_id === $baseCurrency->id
                ? $remainingDebt
                : $debt->currency->convertTo($remainingDebt, $baseCurrency);

            // i_owe decreases capital, owed_to_me increases
            $debtsImpact += $debt->debt_type->capitalImpact() * $remainingInBase;
        }

        $netWorth = $totalRegularBalance + $debtsImpact;

        return [
            'total_balance' => round($totalRegularBalance, 2),
            'debts_impact' => round($debtsImpact, 2),
            'net_worth' => round($netWorth, 2),
            'currency' => $baseCurrency->symbol,
            'currency_code' => $baseCurrency->code,
            'accounts_count' => $regularAccounts->count(),
            'debts_count' => $debts->count(),
        ];
    }

    private function loadBalance(Account $account): Account
    {
        $account->setAttribute('current_balance', $account->current_balance);

        return $account;
    }
}
