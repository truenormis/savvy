<?php

namespace App\Repositories;

use App\DTOs\ReportFilterData;
use App\Models\Budget;

class BudgetRepository
{
    public function getMonthlyBudget(ReportFilterData $filters): ?float
    {
        $hasCategoryOrTagScope = ! empty($filters->categoryIds) || ! empty($filters->tagIds);

        if (! $hasCategoryOrTagScope) {
            if (! empty($filters->accountIds)) {
                return null;
            }

            $globalBudget = Budget::query()
                ->with('currency')
                ->where('is_active', true)
                ->where('is_global', true)
                ->where('period', 'monthly')
                ->first();

            if ($globalBudget) {
                return $this->toBase($globalBudget);
            }

            return $this->sumBudgets(
                Budget::query()->with('currency')->where('is_active', true)->where('period', 'monthly')->get()
            );
        }

        $budgets = Budget::query()
            ->with('currency')
            ->where('is_active', true)
            ->where('period', 'monthly')
            ->where('is_global', false)
            ->where(function ($q) use ($filters) {
                if (! empty($filters->categoryIds)) {
                    $q->orWhereHas('categories', fn ($c) => $c->whereIn('categories.id', $filters->categoryIds));
                }
                if (! empty($filters->tagIds)) {
                    $q->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $filters->tagIds));
                }
            })
            ->get();

        return $this->sumBudgets($budgets);
    }

    private function sumBudgets($budgets): ?float
    {
        $total = 0;
        foreach ($budgets as $budget) {
            $total += $this->toBase($budget);
        }

        return $total > 0 ? $total : null;
    }

    private function toBase(Budget $budget): float
    {
        $amount = (float) $budget->amount;

        return $budget->currency ? $budget->currency->convertToBase($amount) : $amount;
    }
}
