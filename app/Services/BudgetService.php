<?php

namespace App\Services;

use App\Enums\BudgetPeriod;
use App\Models\Budget;
use App\Models\Currency;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function getAll(): Collection
    {
        return Budget::with(['categories', 'currency', 'tags'])->get();
    }

    public function findOrFail(int $id): Budget
    {
        return Budget::with(['categories', 'currency', 'tags'])->findOrFail($id);
    }

    public function create(array $data): Budget
    {
        $categoryIds = $data['category_ids'] ?? [];
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['category_ids'], $data['tag_ids']);

        $budget = Budget::create($data);

        if (!empty($categoryIds)) {
            $budget->categories()->attach($categoryIds);
        }

        if (!empty($tagIds)) {
            $budget->tags()->attach($tagIds);
        }

        return $budget->load(['categories', 'currency', 'tags']);
    }

    public function update(Budget $budget, array $data): Budget
    {
        $categoryIds = $data['category_ids'] ?? null;
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['category_ids'], $data['tag_ids']);

        $budget->update($data);

        if ($categoryIds !== null) {
            $budget->categories()->sync($categoryIds);
        }

        if ($tagIds !== null) {
            $budget->tags()->sync($tagIds);
        }

        return $budget->load(['categories', 'currency', 'tags']);
    }

    public function delete(Budget $budget): void
    {
        $budget->delete();
    }

    public function calculateProgress(Budget $budget): array
    {
        [$startDate, $endDate] = $this->getPeriodDates($budget);

        return $this->getProgress($budget, $startDate, $endDate);
    }

    public function getProgress(Budget $budget, Carbon $startDate, Carbon $endDate): array
    {
        $spent = $this->getSpentAmount($budget, $startDate, $endDate);
        $remaining = max(0, (float) $budget->amount - $spent);
        $percent = $budget->amount > 0 ? round(($spent / (float) $budget->amount) * 100, 1) : 0;

        return [
            'spent' => $spent,
            'remaining' => $remaining,
            'percent' => $percent,
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'is_exceeded' => $spent > (float) $budget->amount,
        ];
    }

    private function getPeriodDates(Budget $budget): array
    {
        $now = Carbon::now();

        if ($budget->period === BudgetPeriod::OneTime) {
            return [
                $budget->start_date ? Carbon::parse($budget->start_date) : $now->copy()->startOfMonth(),
                $budget->end_date ? Carbon::parse($budget->end_date) : $now->copy()->endOfMonth(),
            ];
        }

        $startDate = $budget->start_date ? Carbon::parse($budget->start_date) : null;

        return match ($budget->period) {
            BudgetPeriod::Weekly => [
                $startDate?->copy()->startOfWeek() ?? $now->copy()->startOfWeek(),
                $startDate?->copy()->endOfWeek() ?? $now->copy()->endOfWeek(),
            ],
            BudgetPeriod::Monthly => [
                $startDate?->copy()->startOfMonth() ?? $now->copy()->startOfMonth(),
                $startDate?->copy()->endOfMonth() ?? $now->copy()->endOfMonth(),
            ],
            BudgetPeriod::Yearly => [
                $startDate?->copy()->startOfYear() ?? $now->copy()->startOfYear(),
                $startDate?->copy()->endOfYear() ?? $now->copy()->endOfYear(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    private function getSpentAmount(Budget $budget, Carbon $startDate, Carbon $endDate): float
    {
        // Get target currency (budget's currency or base currency)
        $targetCurrency = $budget->currency ?? Currency::getBase();
        $targetRate = $targetCurrency ? (float) $targetCurrency->rate : 1;

        $query = Transaction::query()
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->join('currencies', 'accounts.currency_id', '=', 'currencies.id')
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.date', [$startDate, $endDate]);

        // Filter by categories if not global
        if (!$budget->is_global) {
            $categoryIds = $budget->categories->pluck('id')->toArray();
            if (empty($categoryIds)) {
                return 0;
            }
            $query->whereIn('transactions.category_id', $categoryIds);
        }

        // Filter by tags if budget has tags attached
        $tagIds = $budget->tags->pluck('id')->toArray();
        if (!empty($tagIds)) {
            $query->whereExists(function ($subQuery) use ($tagIds) {
                $subQuery->select(DB::raw(1))
                    ->from('transaction_tag')
                    ->whereColumn('transaction_tag.transaction_id', 'transactions.id')
                    ->whereIn('transaction_tag.tag_id', $tagIds);
            });
        }

        // Convert to base currency first, then to target currency
        // amount * source_rate = base amount
        // base amount / target_rate = target amount
        $totalInBase = (float) $query->select(DB::raw('SUM(transactions.amount * currencies.rate) as total'))
            ->value('total');

        return $targetRate > 0 ? $totalInBase / $targetRate : $totalInBase;
    }
}
