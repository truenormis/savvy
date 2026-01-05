<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\Models\Currency;
use App\Repositories\TransactionRepository;

class OverviewReportService
{
    public function __construct(
        private TransactionRepository $transactionRepository
    ) {}

    public function getMetrics(ReportFilterData $filters): array
    {
        $dateRange = $filters->getDateRange();
        $comparisonRange = $filters->getComparisonDateRange();

        $current = $this->calculateMetrics($dateRange, $filters);
        $previous = $comparisonRange ? $this->calculateMetrics($comparisonRange, $filters) : null;
        $sparkline = $this->getSparklineData($filters);

        return [
            'income' => [
                'value' => $current['income'],
                'previous' => $previous['income'] ?? null,
                'sparkline' => $sparkline['income'],
            ],
            'expenses' => [
                'value' => $current['expenses'],
                'previous' => $previous['expenses'] ?? null,
                'sparkline' => $sparkline['expenses'],
            ],
            'netCashFlow' => [
                'value' => $current['net'],
                'previous' => $previous['net'] ?? null,
                'sparkline' => $sparkline['net'],
            ],
            'savingsRate' => [
                'value' => $current['savingsRate'],
                'previous' => $previous['savingsRate'] ?? null,
                'sparkline' => $sparkline['savingsRate'],
            ],
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    private function calculateMetrics(array $dateRange, ReportFilterData $filters): array
    {
        $income = $this->transactionRepository->sumByType('income', $dateRange, $filters);
        $expenses = $this->transactionRepository->sumByType('expense', $dateRange, $filters);
        $net = $income - $expenses;
        $savingsRate = $income > 0 ? round(($net / $income) * 100, 1) : 0;

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'net' => round($net, 2),
            'savingsRate' => $savingsRate,
        ];
    }

    private function getSparklineData(ReportFilterData $filters): array
    {
        $periods = $filters->getSparklinePeriods(6);

        $income = [];
        $expenses = [];
        $net = [];
        $savingsRate = [];

        foreach ($periods as $period) {
            $metrics = $this->calculateMetrics($period, $filters);
            $income[] = $metrics['income'];
            $expenses[] = $metrics['expenses'];
            $net[] = $metrics['net'];
            $savingsRate[] = $metrics['savingsRate'];
        }

        return compact('income', 'expenses', 'net', 'savingsRate');
    }
}
