<?php

namespace App\Enums;

enum TriggerType: string
{
    case OnTransactionCreate = 'on_transaction_create';
    case OnTransactionUpdate = 'on_transaction_update';

    public function label(): string
    {
        return match ($this) {
            self::OnTransactionCreate => 'On Transaction Create',
            self::OnTransactionUpdate => 'On Transaction Update',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OnTransactionCreate => 'Triggers when a new transaction is created',
            self::OnTransactionUpdate => 'Triggers when a transaction is updated',
        };
    }
}
