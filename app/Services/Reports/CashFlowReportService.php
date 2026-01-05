<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\Models\Currency;
use App\Repositories\TransactionRepository;

class CashFlowReportService
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private PeriodGenerator $periodGenerator
    ) {}

    public function getMoneyFlow(ReportFilterData $filters): array
    {
        $dateRange = $filters->getDateRange();

        $incomeByCategory = $this->transactionRepository->sumGroupedByCategory('income', $dateRange, $filters);
        $expensesByCategory = $this->transactionRepository->sumGroupedByCategory('expense', $dateRange, $filters);

        $totalIncome = array_sum(array_column($incomeByCategory, 'total'));
        $totalExpenses = array_sum(array_column($expensesByCategory, 'total'));

        $nodes = $this->buildNodes($incomeByCategory, $expensesByCategory, $totalIncome - $totalExpenses);
        $links = $this->buildLinks($incomeByCategory, $expensesByCategory, $totalIncome, $totalExpenses);

        return [
            'nodes' => $nodes,
            'links' => $links,
            'totals' => [
                'income' => round($totalIncome, 2),
                'expenses' => round($totalExpenses, 2),
                'savings' => round(max(0, $totalIncome - $totalExpenses), 2),
            ],
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    public function getOverTime(ReportFilterData $filters, string $groupBy = 'day'): array
    {
        $dateRange = $filters->getDateRange();
        $comparisonRange = $filters->getComparisonDateRange();

        $currentData = $this->getGroupedCashFlow($dateRange, $filters, $groupBy);
        $comparisonData = $comparisonRange ? $this->getGroupedCashFlow($comparisonRange, $filters, $groupBy) : [];

        $result = [];
        $balance = 0;
        $prevBalance = 0;

        foreach ($currentData as $index => $item) {
            $balance += $item['income'] - $item['expenses'];

            $entry = [
                'label' => $item['label'],
                'income' => $item['income'],
                'expenses' => $item['expenses'],
                'balance' => round($balance, 2),
            ];

            if (isset($comparisonData[$index])) {
                $prevBalance += $comparisonData[$index]['income'] - $comparisonData[$index]['expenses'];
                $entry['prevIncome'] = $comparisonData[$index]['income'];
                $entry['prevExpenses'] = $comparisonData[$index]['expenses'];
                $entry['prevBalance'] = round($prevBalance, 2);
            }

            $result[] = $entry;
        }

        return [
            'items' => $result,
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    private function getGroupedCashFlow(array $dateRange, ReportFilterData $filters, string $groupBy): array
    {
        $startDate = $dateRange['start']->copy();
        $endDate = $dateRange['end']->copy();

        $sqlFormat = $this->periodGenerator->getSqlFormat($groupBy);

        $incomeData = $this->transactionRepository->getGroupedByPeriod('income', $dateRange, $sqlFormat, $filters);
        $expensesData = $this->transactionRepository->getGroupedByPeriod('expense', $dateRange, $sqlFormat, $filters);

        $periods = $this->periodGenerator->generate($startDate, $endDate, $groupBy);

        $result = [];
        foreach ($periods as $period) {
            $periodKey = $period['key'];
            $result[] = [
                'label' => $period['label'],
                'income' => round((float) ($incomeData[$periodKey] ?? 0), 2),
                'expenses' => round((float) ($expensesData[$periodKey] ?? 0), 2),
            ];
        }

        return $result;
    }

    private function buildNodes(array $incomeByCategory, array $expensesByCategory, float $savings): array
    {
        $incomeColors = ['#22c55e', '#16a34a', '#15803d', '#14532d', '#166534', '#4ade80'];
        $expenseColors = ['#ef4444', '#f97316', '#eab308', '#ec4899', '#8b5cf6', '#06b6d4', '#f43f5e', '#a855f7'];

        $nodes = [];

        foreach ($incomeByCategory as $index => $item) {
            $nodes[] = [
                'name' => $item['name'],
                'itemStyle' => ['color' => $incomeColors[$index % count($incomeColors)]],
            ];
        }

        foreach ($expensesByCategory as $index => $item) {
            $nodes[] = [
                'name' => $item['name'],
                'itemStyle' => ['color' => $expenseColors[$index % count($expenseColors)]],
            ];
        }

        if ($savings > 0) {
            $nodes[] = [
                'name' => 'Savings',
                'itemStyle' => ['color' => '#3b82f6'],
            ];
        }

        return $nodes;
    }

    private function buildLinks(array $incomeByCategory, array $expensesByCategory, float $totalIncome, float $totalExpenses): array
    {
        $links = [];
        $savings = $totalIncome - $totalExpenses;

        if ($totalIncome <= 0 || $totalExpenses <= 0) {
            return $links;
        }

        foreach ($incomeByCategory as $income) {
            $incomeShare = $income['total'] / $totalIncome;

            foreach ($expensesByCategory as $expense) {
                $linkValue = round($incomeShare * $expense['total'], 2);
                if ($linkValue > 0) {
                    $links[] = [
                        'source' => $income['name'],
                        'target' => $expense['name'],
                        'value' => $linkValue,
                    ];
                }
            }

            if ($savings > 0) {
                $savingsFromSource = round($incomeShare * $savings, 2);
                if ($savingsFromSource > 0) {
                    $links[] = [
                        'source' => $income['name'],
                        'target' => 'Savings',
                        'value' => $savingsFromSource,
                    ];
                }
            }
        }

        return $links;
    }
}
