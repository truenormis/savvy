<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Transfer => 'Transfer',
        };
    }

    public function isTransfer(): bool
    {
        return $this === self::Transfer;
    }

    public function affectsBalance(): int
    {
        return match ($this) {
            self::Income => 1,
            self::Expense => -1,
            self::Transfer => -1,
        };
    }
}
