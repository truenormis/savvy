<?php

namespace App\Repositories;

use App\DTOs\ReportFilterData;
use App\Models\Budget;

class BudgetRepository
{
    public function getMonthlyBudget(ReportFilterData $filters): ?float
    {
        $globalBudget = Budget::query()
            ->with('currency')
            ->where('is_active', true)
            ->where('is_global', true)
            ->where('period', 'monthly')
            ->first();

        if ($globalBudget) {
            $amount = (float) $globalBudget->amount;
            if ($globalBudget->currency) {
                $amount = $globalBudget->currency->convertToBase($amount);
            }
            return $amount;
        }

        $budgetsQuery = Budget::query()
            ->with('currency')
            ->where('is_active', true)
            ->where('period', 'monthly');

        if (!empty($filters->categoryIds)) {
            $budgetsQuery->whereHas('categories', function ($q) use ($filters) {
                $q->whereIn('categories.id', $filters->categoryIds);
            });
        }

        $budgets = $budgetsQuery->get();

        $totalBudget = 0;
        foreach ($budgets as $budget) {
            $amount = (float) $budget->amount;
            if ($budget->currency) {
                $amount = $budget->currency->convertToBase($amount);
            }
            $totalBudget += $amount;
        }

        return $totalBudget > 0 ? $totalBudget : null;
    }
}
