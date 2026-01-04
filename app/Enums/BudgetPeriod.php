<?php

namespace App\Enums;

enum BudgetPeriod: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case OneTime = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
            self::OneTime => 'One-time',
        };
    }
}
