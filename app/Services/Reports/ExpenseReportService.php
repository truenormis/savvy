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

        $months = $this->splitIntoMonths($startDate, $endDate);

        $allDailyExpenses = $this->transactionRepository->getDailyTotals(
            'expense',
            $startDate,
            $endDate,
            $filters
        );

        $today = Carbon::now()->startOfDay();
        $budget = $this->budgetRepository->getMonthlyBudget($filters);

        $monthsData = [];
        foreach ($months as $month) {
            $monthStart = $month['start'];
            $monthEnd = $month['end'];
            $daysInMonth = $monthStart->diffInDays($monthEnd) + 1;

            $cumulativeExpenses = $this->calculateCumulativeForMonth(
                $allDailyExpenses,
                $monthStart,
                $daysInMonth
            );

            $currentDay = $this->determineCurrentDay($today, $monthStart, $monthEnd);
            $totalSpent = end($cumulativeExpenses) ?: 0;

            $monthsData[] = [
                'label' => $monthStart->format('M Y'),
                'budget' => $budget,
                'dailyExpenses' => $cumulativeExpenses,
                'currentDay' => $currentDay,
                'daysInMonth' => $daysInMonth,
                'totalSpent' => $totalSpent,
                'monthStart' => $monthStart->toDateString(),
                'monthEnd' => $monthEnd->toDateString(),
            ];
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

        usort($categories, fn($a, $b) => $b['current'] <=> $a['current']);

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

    private function splitIntoMonths(Carbon $startDate, Carbon $endDate): array
    {
        $months = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current->lte($endDate)) {
            $monthStart = $current->copy();
            if ($monthStart->lt($startDate)) {
                $monthStart = $startDate->copy();
            }

            $monthEnd = $current->copy()->endOfMonth()->startOfDay();
            if ($monthEnd->gt($endDate)) {
                $monthEnd = $endDate->copy()->startOfDay();
            }

            $months[] = [
                'start' => $monthStart,
                'end' => $monthEnd,
            ];

            $current->addMonth()->startOfMonth();
        }

        return $months;
    }

    private function calculateCumulativeForMonth(array $dailyExpenses, Carbon $monthStart, int $daysInMonth): array
    {
        $cumulative = [];
        $total = 0;

        for ($i = 0; $i < $daysInMonth; $i++) {
            $date = $monthStart->copy()->addDays($i)->toDateString();
            $dayExpense = $dailyExpenses[$date]['total'] ?? 0;
            $total += $dayExpense;
            $cumulative[] = round($total, 2);
        }

        return $cumulative;
    }

    private function determineCurrentDay(Carbon $today, Carbon $startDate, Carbon $endDate): ?int
    {
        if ($today->gte($startDate) && $today->lte($endDate)) {
            return (int) $startDate->diffInDays($today) + 1;
        }

        return null;
    }
}