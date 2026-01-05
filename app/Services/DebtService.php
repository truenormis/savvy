<?php

namespace App\Services;

use App\DTOs\DebtData;
use App\DTOs\DebtPaymentData;
use App\Enums\DebtType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DebtService
{
    public function getAll(bool $includeCompleted = false): Collection
    {
        $query = Account::with('currency')
            ->where('type', 'debt');

        if (!$includeCompleted) {
            $query->where('is_paid_off', false);
        }

        return $query->get()->each(fn (Account $debt) => $this->loadDebtData($debt));
    }

    public function getByType(DebtType $type): Collection
    {
        return Account::with('currency')
            ->where('type', 'debt')
            ->where('debt_type', $type)
            ->where('is_paid_off', false)
            ->get()
            ->each(fn (Account $debt) => $this->loadDebtData($debt));
    }

    public function findOrFail(int $id): Account
    {
        $debt = Account::with('currency')
            ->where('type', 'debt')
            ->findOrFail($id);

        return $this->loadDebtData($debt);
    }

    public function create(DebtData $data): Account
    {
        $debt = Account::create([
            'name' => $data->name,
            'type' => 'debt',
            'debt_type' => $data->debtType,
            'currency_id' => $data->currencyId,
            'initial_balance' => 0,
            'target_amount' => $data->amount,
            'due_date' => $data->dueDate,
            'counterparty' => $data->counterparty,
            'debt_description' => $data->description,
            'is_active' => true,
            'is_paid_off' => false,
        ]);

        $debt->load('currency');

        return $this->loadDebtData($debt);
    }

    public function update(Account $debt, DebtData $data): Account
    {
        if (!$debt->isDebt()) {
            throw new DomainException('Account is not a debt.');
        }

        $debt->update([
            'name' => $data->name,
            'debt_type' => $data->debtType,
            'currency_id' => $data->currencyId,
            'target_amount' => $data->amount,
            'due_date' => $data->dueDate,
            'counterparty' => $data->counterparty,
            'debt_description' => $data->description,
        ]);

        $debt->load('currency');

        return $this->loadDebtData($debt);
    }

    /**
     * Make a payment for "I owe" debt type
     */
    public function makePayment(Account $debt, Account $sourceAccount, DebtPaymentData $data): Transaction
    {
        if (!$debt->isDebt()) {
            throw new DomainException('Account is not a debt.');
        }

        if ($debt->debt_type !== DebtType::IOwe) {
            throw new DomainException('Payment can only be made for "I owe" debts.');
        }

        if ($debt->is_paid_off) {
            throw new DomainException('Debt is already paid off.');
        }

        if ($sourceAccount->isDebt()) {
            throw new DomainException('Cannot use debt account as payment source.');
        }

        return DB::transaction(function () use ($debt, $sourceAccount, $data) {
            $toAmount = $this->calculateToAmount($sourceAccount, $debt, $data->amount);

            $transaction = Transaction::create([
                'type' => TransactionType::DebtPayment,
                'account_id' => $sourceAccount->id,
                'to_account_id' => $debt->id,
                'amount' => $data->amount,
                'to_amount' => $toAmount,
                'exchange_rate' => $data->amount > 0 ? round($toAmount / $data->amount, 6) : null,
                'description' => $data->description ?? "Payment for debt: {$debt->name}",
                'date' => $data->date,
                'category_id' => null,
            ]);

            $debt->refresh();
            $debt->checkAndMarkAsPaidOff();

            return $transaction->load(['account.currency', 'toAccount.currency']);
        });
    }

    /**
     * Collect payment for "Owed to me" debt type
     */
    public function collectPayment(Account $debt, Account $targetAccount, DebtPaymentData $data): Transaction
    {
        if (!$debt->isDebt()) {
            throw new DomainException('Account is not a debt.');
        }

        if ($debt->debt_type !== DebtType::OwedToMe) {
            throw new DomainException('Collection can only be made for "Owed to me" debts.');
        }

        if ($debt->is_paid_off) {
            throw new DomainException('Debt is already paid off.');
        }

        if ($targetAccount->isDebt()) {
            throw new DomainException('Cannot use debt account as target.');
        }

        return DB::transaction(function () use ($debt, $targetAccount, $data) {
            $toAmount = $this->calculateToAmount($debt, $targetAccount, $data->amount);

            $transaction = Transaction::create([
                'type' => TransactionType::DebtCollection,
                'account_id' => $targetAccount->id,
                'to_account_id' => $debt->id,
                'amount' => $toAmount,
                'to_amount' => $data->amount,
                'exchange_rate' => $data->amount > 0 ? round($toAmount / $data->amount, 6) : null,
                'description' => $data->description ?? "Collection for debt: {$debt->name}",
                'date' => $data->date,
                'category_id' => null,
            ]);

            $debt->refresh();
            $debt->checkAndMarkAsPaidOff();

            return $transaction->load(['account.currency', 'toAccount.currency']);
        });
    }

    public function getSummary(): array
    {
        $baseCurrency = Currency::getBase();

        if (!$baseCurrency) {
            return [
                'total_i_owe' => 0,
                'total_owed_to_me' => 0,
                'net_debt' => 0,
                'debts_count' => 0,
                'currency' => '$',
            ];
        }

        $debts = $this->getAll();

        $totalIOwe = 0;
        $totalOwedToMe = 0;

        foreach ($debts as $debt) {
            $remainingInBase = $debt->currency->convertToBase($debt->current_balance);

            if ($debt->debt_type === DebtType::IOwe) {
                $totalIOwe += $remainingInBase;
            } else {
                $totalOwedToMe += $remainingInBase;
            }
        }

        return [
            'total_i_owe' => round($totalIOwe, 2),
            'total_owed_to_me' => round($totalOwedToMe, 2),
            'net_debt' => round($totalOwedToMe - $totalIOwe, 2),
            'debts_count' => $debts->count(),
            'currency' => $baseCurrency->symbol,
        ];
    }

    public function delete(Account $debt): void
    {
        if (!$debt->isDebt()) {
            throw new DomainException('Account is not a debt.');
        }

        $hasPayments = Transaction::where('to_account_id', $debt->id)->exists();

        if ($hasPayments) {
            throw new DomainException('Cannot delete debt with payment history.');
        }

        $debt->delete();
    }

    public function reopen(Account $debt): Account
    {
        if (!$debt->isDebt()) {
            throw new DomainException('Account is not a debt.');
        }

        if (!$debt->is_paid_off) {
            throw new DomainException('Debt is not paid off.');
        }

        $debt->update(['is_paid_off' => false]);

        return $this->loadDebtData($debt);
    }

    private function loadDebtData(Account $debt): Account
    {
        $debt->setAttribute('current_balance', $debt->current_balance);
        $debt->setAttribute('remaining_debt', $debt->remaining_debt);
        $debt->setAttribute('payment_progress', $debt->payment_progress);

        return $debt;
    }

    private function calculateToAmount(Account $fromAccount, Account $toAccount, float $amount): float
    {
        if ($fromAccount->currency_id === $toAccount->currency_id) {
            return $amount;
        }

        return $fromAccount->currency->convertTo($amount, $toAccount->currency);
    }
}
