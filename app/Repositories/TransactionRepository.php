<?php

namespace App\Repositories;

use App\DTOs\ReportFilterData;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionRepository
{
    public function sumByType(string $type, array $dateRange, ReportFilterData $filters): float
    {
        $query = $this->baseQuery()
            ->where('transactions.type', $type)
            ->whereBetween('transactions.date', [
                $dateRange['start']->toDateString(),
                $dateRange['end']->toDateString(),
            ]);

        $this->applyFilters($query, $filters);

        return (float) $query
            ->select(DB::raw('COALESCE(SUM(transactions.amount * currencies.rate), 0) as total'))
            ->value('total');
    }

    public function sumGroupedByCategory(string $type, array $dateRange, ReportFilterData $filters): array
    {
        $query = $this->baseQuery()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.type', $type)
            ->whereNotNull('transactions.category_id')
            ->whereBetween('transactions.date', [
                $dateRange['start']->toDateString(),
                $dateRange['end']->toDateString(),
            ]);

        $this->applyFilters($query, $filters);

        return $query
            ->select(
                'categories.id',
                'categories.name',
                'categories.icon',
                'categories.color',
                DB::raw('SUM(transactions.amount * currencies.rate) as total')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'icon' => $row->icon ?? 'circle',
                'color' => $row->color ?? '#64748b',
                'total' => round((float) $row->total, 2),
            ])
            ->toArray();
    }

    public function getDailyTotals(string $type, Carbon $startDate, Carbon $endDate, ReportFilterData $filters): array
    {
        $query = $this->baseQuery()
            ->where('transactions.type', $type)
            ->whereBetween('transactions.date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);

        $this->applyFilters($query, $filters);

        $results = $query
            ->select(
                DB::raw('DATE(transactions.date) as day_date'),
                DB::raw('SUM(transactions.amount * currencies.rate) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('day_date')
            ->orderBy('day_date')
            ->get();

        $data = [];
        foreach ($results as $row) {
            $data[$row->day_date] = [
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->count,
            ];
        }

        return $data;
    }

    public function getGroupedByPeriod(
        string $type,
        array $dateRange,
        string $sqlFormat,
        ReportFilterData $filters,
        ?int $categoryId = null
    ): array {
        $query = $this->baseQuery()
            ->where('transactions.type', $type)
            ->whereBetween('transactions.date', [
                $dateRange['start']->toDateString(),
                $dateRange['end']->toDateString(),
            ]);

        if ($categoryId !== null) {
            $query->where('transactions.category_id', $categoryId);
        }

        $this->applyFilters($query, $filters);

        return $query
            ->select(
                DB::raw("$sqlFormat as period_date"),
                DB::raw('SUM(transactions.amount * currencies.rate) as total')
            )
            ->groupBy('period_date')
            ->orderBy('period_date')
            ->pluck('total', 'period_date')
            ->toArray();
    }

    public function getTopByAmount(string $type, array $dateRange, ReportFilterData $filters, int $limit): array
    {
        $query = $this->baseQuery()
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.type', $type)
            ->whereBetween('transactions.date', [
                $dateRange['start']->toDateString(),
                $dateRange['end']->toDateString(),
            ]);

        $this->applyFilters($query, $filters);

        return $query
            ->select(
                'transactions.id',
                'transactions.description',
                'transactions.date',
                DB::raw('transactions.amount * currencies.rate as converted_amount'),
                'categories.id as category_id',
                'categories.name as category_name',
                'categories.icon as category_icon',
                'categories.color as category_color',
                'accounts.id as account_id',
                'accounts.name as account_name'
            )
            ->orderByDesc('converted_amount')
            ->limit($limit)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'description' => $t->description,
                'amount' => round((float) $t->converted_amount, 2),
                'date' => $t->date instanceof Carbon ? $t->date->toDateString() : $t->date,
                'category' => $t->category_id ? [
                    'id' => $t->category_id,
                    'name' => $t->category_name,
                    'icon' => $t->category_icon ?? 'circle',
                    'color' => $t->category_color ?? '#64748b',
                ] : null,
                'account' => [
                    'id' => $t->account_id,
                    'name' => $t->account_name,
                ],
            ])
            ->toArray();
    }

    private function baseQuery()
    {
        return Transaction::query()
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->join('currencies', 'accounts.currency_id', '=', 'currencies.id');
    }

    private function applyFilters($query, ReportFilterData $filters): void
    {
        if (!empty($filters->accountIds)) {
            $query->whereIn('transactions.account_id', $filters->accountIds);
        }

        if (!empty($filters->categoryIds)) {
            $query->whereIn('transactions.category_id', $filters->categoryIds);
        }

        if (!empty($filters->tagIds)) {
            $query->whereExists(function ($subQuery) use ($filters) {
                $subQuery->select(DB::raw(1))
                    ->from('transaction_tag')
                    ->whereColumn('transaction_tag.transaction_id', 'transactions.id')
                    ->whereIn('transaction_tag.tag_id', $filters->tagIds);
            });
        }
    }
}
