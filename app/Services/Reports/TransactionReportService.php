<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\Models\Currency;
use App\Repositories\TransactionRepository;

class TransactionReportService
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private PeriodGenerator $periodGenerator
    ) {}

    public function getSummary(ReportFilterData $filters, string $type): array
    {
        $dateRange = $filters->getDateRange();
        $comparisonRange = $filters->getComparisonDateRange();

        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        $daysInPeriod = (int) $startDate->diffInDays($endDate) + 1;

        $total = $this->transactionRepository->sumByType($type, $dateRange, $filters);

        $previous = null;
        $prevDaysInPeriod = null;
        if ($comparisonRange) {
            $previous = $this->transactionRepository->sumByType($type, $comparisonRange, $filters);
            $prevDaysInPeriod = (int) $comparisonRange['start']->diffInDays($comparisonRange['end']) + 1;
        }

        $avgPerDay = $daysInPeriod > 0 ? round($total / $daysInPeriod, 2) : 0;
        $avgPerWeek = $daysInPeriod > 0 ? round($total / ($daysInPeriod / 7), 2) : 0;

        $prevAvgPerDay = null;
        $prevAvgPerWeek = null;
        if ($previous !== null && $prevDaysInPeriod > 0) {
            $prevAvgPerDay = round($previous / $prevDaysInPeriod, 2);
            $prevAvgPerWeek = round($previous / ($prevDaysInPeriod / 7), 2);
        }

        return [
            'total' => round($total, 2),
            'previous' => $previous !== null ? round($previous, 2) : null,
            'avgPerDay' => $avgPerDay,
            'avgPerWeek' => $avgPerWeek,
            'prevAvgPerDay' => $prevAvgPerDay,
            'prevAvgPerWeek' => $prevAvgPerWeek,
            'daysInPeriod' => $daysInPeriod,
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    public function getByCategory(ReportFilterData $filters, string $type): array
    {
        $dateRange = $filters->getDateRange();
        $categories = $this->transactionRepository->sumGroupedByCategory($type, $dateRange, $filters);

        $total = array_sum(array_column($categories, 'total'));

        $items = [];
        foreach ($categories as $category) {
            $items[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'value' => $category['total'],
                'percentage' => $total > 0 ? round(($category['total'] / $total) * 100, 1) : 0,
            ];
        }

        return [
            'items' => $items,
            'total' => round($total, 2),
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    public function getDynamics(ReportFilterData $filters, string $type, string $groupBy = 'day'): array
    {
        $dateRange = $filters->getDateRange();
        $startDate = $dateRange['start']->copy();
        $endDate = $dateRange['end']->copy();

        $categoriesData = $this->transactionRepository->sumGroupedByCategory($type, $dateRange, $filters);
        $periods = $this->periodGenerator->generate($startDate, $endDate, $groupBy);
        $labels = array_column($periods, 'label');
        $sqlFormat = $this->periodGenerator->getSqlFormat($groupBy);

        $totalByPeriod = $this->transactionRepository->getGroupedByPeriod($type, $dateRange, $sqlFormat, $filters);

        $totalData = [];
        foreach ($periods as $period) {
            $totalData[] = round((float) ($totalByPeriod[$period['key']] ?? 0), 2);
        }

        $datasets = [
            [
                'id' => 0,
                'name' => 'Total',
                'color' => $type === 'expense' ? '#ef4444' : '#22c55e',
                'data' => $totalData,
            ],
        ];

        foreach ($categoriesData as $category) {
            $categoryByPeriod = $this->transactionRepository->getGroupedByPeriod(
                $type,
                $dateRange,
                $sqlFormat,
                $filters,
                $category['id']
            );

            $categoryData = [];
            foreach ($periods as $period) {
                $categoryData[] = round((float) ($categoryByPeriod[$period['key']] ?? 0), 2);
            }

            $datasets[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                'color' => $category['color'],
                'data' => $categoryData,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    public function getTop(ReportFilterData $filters, string $type, int $limit = 10): array
    {
        $dateRange = $filters->getDateRange();
        $items = $this->transactionRepository->getTopByAmount($type, $dateRange, $filters, $limit);

        return [
            'items' => $items,
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }
}
