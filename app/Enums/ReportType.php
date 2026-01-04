<?php

namespace App\Enums;

enum ReportType: string
{
    case Expenses = 'expenses';
    case Income = 'income';
    case ExpensesAndIncome = 'expenses_and_income';
    case Balance = 'balance';
    case CashFlow = 'cash_flow';
    case Budgets = 'budgets';
}
