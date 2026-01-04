<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function getAll(?string $type = null): Collection
    {
        $query = Category::withCount('transactions');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    public function findOrFail(int $id): Category
    {
        return Category::withCount('transactions')->findOrFail($id);
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    public function delete(Category $category): void
    {
        if ($category->transactions()->exists()) {
            throw new \DomainException('Cannot delete category that has transactions.');
        }

        $category->delete();
    }

    public function getStatistics(int $categoryId, ?string $startDate = null, ?string $endDate = null): array
    {
        $category = Category::findOrFail($categoryId);

        $query = $category->transactions();

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'type' => $category->type,
            'transactions_count' => $query->count(),
            'total_amount' => (float) $query->sum('amount'),
        ];
    }

    public function getSummaryByType(string $type, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $baseCurrency = Currency::getBase();

        // Get totals converted to base currency using SQL
        $query = Transaction::query()
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->join('currencies', 'accounts.currency_id', '=', 'currencies.id')
            ->whereNotNull('transactions.category_id')
            ->where('transactions.type', $type)
            ->select('transactions.category_id', DB::raw('SUM(transactions.amount * currencies.rate) as total_in_base'))
            ->groupBy('transactions.category_id');

        if ($startDate) {
            $query->where('transactions.date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('transactions.date', '<=', $endDate);
        }

        $totals = $query->pluck('total_in_base', 'category_id');

        return Category::where('type', $type)
            ->withCount(['transactions' => function ($query) use ($startDate, $endDate) {
                $this->applyDateFilter($query, $startDate, $endDate);
            }])
            ->get()
            ->map(function ($category) use ($totals, $baseCurrency) {
                $category->total_amount = (float) ($totals[$category->id] ?? 0);
                $category->currency = $baseCurrency?->symbol ?? '';
                return $category;
            });
    }

    private function applyDateFilter($query, ?string $startDate, ?string $endDate): void
    {
        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }
    }
}
