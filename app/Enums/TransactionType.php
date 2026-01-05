<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case DebtPayment = 'debt_payment';
    case DebtCollection = 'debt_collection';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Transfer => 'Transfer',
            self::DebtPayment => 'Debt Payment',
            self::DebtCollection => 'Debt Collection',
        };
    }

    public function isTransfer(): bool
    {
        return $this === self::Transfer;
    }

    public function isDebtOperation(): bool
    {
        return in_array($this, [self::DebtPayment, self::DebtCollection]);
    }

    /**
     * Whether this transaction type affects income/expense categories
     */
    public function affectsIncomeExpense(): bool
    {
        return in_array($this, [self::Income, self::Expense]);
    }

    public function affectsBalance(): int
    {
        return match ($this) {
            self::Income => 1,
            self::Expense => -1,
            self::Transfer => -1,
            self::DebtPayment => -1,
            self::DebtCollection => 1,
        };
    }
}
