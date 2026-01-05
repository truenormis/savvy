<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class AccountBalanceRepository
{
    public function getBalanceAtDate(Account $account, string $date): float
    {
        $initial = (float) $account->initial_balance;

        $income = Transaction::where('account_id', $account->id)
            ->whereIn('type', ['income', 'debt_collection'])
            ->where('date', '<=', $date)
            ->sum('amount');

        $expense = Transaction::where('account_id', $account->id)
            ->whereIn('type', ['expense', 'debt_payment'])
            ->where('date', '<=', $date)
            ->sum('amount');

        $transferOut = Transaction::where('account_id', $account->id)
            ->where('type', 'transfer')
            ->where('date', '<=', $date)
            ->sum('amount');

        $transferIn = Transaction::where('to_account_id', $account->id)
            ->where('date', '<=', $date)
            ->sum('to_amount');

        return $initial + $income - $expense - $transferOut + $transferIn;
    }

    public function getBalancesAtDate(Collection $accounts, string $date): array
    {
        $result = [];

        foreach ($accounts as $account) {
            $balance = $this->getBalanceAtDate($account, $date);
            $rate = $account->currency->rate ?? 1;

            $result[] = [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $balance * $rate,
            ];
        }

        return $result;
    }
}
