<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\Models\Currency;
use App\Repositories\BudgetRepository;
use App\Repositories\TransactionRepository;
use Carbon\Carbon;

class ExpenseReportService
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private BudgetRepository $budgetRepository
    ) {}

    public function getPace(ReportFilterData $filters): array
    {
        $dateRange = $filters->getDateRange();
        $startDate = $dateRange['start']->copy()->startOfDay();
        $endDate = $dateRange['end']->copy()->endOfDay();

        $allDailyExpenses = $this->transactionRepository->getDailyTotals(
            'expense',
            $startDate,
            $endDate,
            $filters
        );

        $today = Carbon::now()->startOfDay();
        $budget = $this->budgetRepository->getMonthlyBudget($filters);

        $monthsData = [];
        $cursor = $startDate->copy()->startOfMonth();

        while ($cursor->lte($endDate)) {
            if ($cursor->greaterThan($today) && ! $cursor->isSameMonth($today)) {
                break;
            }

            $monthStart = $cursor->copy();
            $daysInMonth = $monthStart->daysInMonth;

            $cumulative = [];
            $total = 0;
            for ($i = 0; $i < $daysInMonth; $i++) {
                $day = $monthStart->copy()->addDays($i);
                if ($day->betweenIncluded($startDate, $endDate)) {
                    $total += $allDailyExpenses[$day->toDateString()]['total'] ?? 0;
                }
                $cumulative[] = round($total, 2);
            }

            $monthsData[] = [
                'label' => $monthStart->format('M Y'),
                'budget' => $budget,
                'dailyExpenses' => $cumulative,
                'currentDay' => $today->isSameMonth($monthStart) ? $today->day : null,
                'daysInMonth' => $daysInMonth,
                'totalSpent' => end($cumulative) ?: 0,
                'monthStart' => $monthStart->toDateString(),
                'monthEnd' => $monthStart->copy()->endOfMonth()->toDateString(),
            ];

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return [
            'months' => $monthsData,
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    public function getByCategory(ReportFilterData $filters): array
    {
        $dateRange = $filters->getDateRange();
        $comparisonRange = $filters->getComparisonDateRange();

        $currentExpenses = $this->transactionRepository->sumGroupedByCategory('expense', $dateRange, $filters);

        $previousExpenses = [];
        if ($comparisonRange) {
            $previousData = $this->transactionRepository->sumGroupedByCategory('expense', $comparisonRange, $filters);
            foreach ($previousData as $item) {
                $previousExpenses[$item['id']] = $item['total'];
            }
        }

        $categories = [];
        foreach ($currentExpenses as $expense) {
            $categories[] = [
                'id' => $expense['id'],
                'name' => $expense['name'],
                'icon' => $expense['icon'],
                'color' => $expense['color'],
                'current' => $expense['total'],
                'previous' => $previousExpenses[$expense['id']] ?? 0,
            ];
        }

        usort($categories, fn ($a, $b) => $b['current'] <=> $a['current']);

        return [
            'categories' => $categories,
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    public function getHeatmap(ReportFilterData $filters): array
    {
        $dateRange = $filters->getDateRange();
        $startDate = $dateRange['start']->copy();
        $endDate = $dateRange['end']->copy();

        $dailyData = $this->transactionRepository->getDailyTotals('expense', $startDate, $endDate, $filters);

        $data = [];
        $max = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();
            $dayData = $dailyData[$dateStr] ?? ['total' => 0, 'count' => 0];

            $data[] = [
                'date' => $dateStr,
                'value' => $dayData['total'],
                'count' => $dayData['count'],
            ];

            if ($dayData['total'] > $max) {
                $max = $dayData['total'];
            }

            $current->addDay();
        }

        return [
            'items' => $data,
            'max' => round($max, 2),
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }
}
