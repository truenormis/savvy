<?php

namespace App\Http\Controllers;

use App\DTOs\ReportConfigData;
use App\Enums\ReportCompareWith;
use App\Enums\ReportGroupBy;
use App\Enums\ReportMetric;
use App\Enums\ReportType;
use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    /**
     * Generate a report based on configuration
     */
    public function generate(ReportRequest $request): JsonResponse
    {
        $config = ReportConfigData::fromArray($request->validated());
        $report = $this->reportService->generate($config);

        return response()->json($report);
    }

    /**
     * Get available report options for the frontend
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'types' => array_map(fn($t) => [
                'value' => $t->value,
                'label' => $this->getTypeLabel($t),
            ], ReportType::cases()),

            'group_by' => array_map(fn($g) => [
                'value' => $g->value,
                'label' => $this->getGroupByLabel($g),
            ], ReportGroupBy::cases()),

            'metrics' => array_map(fn($m) => [
                'value' => $m->value,
                'label' => $this->getMetricLabel($m),
            ], ReportMetric::cases()),

            'compare_with' => array_map(fn($c) => [
                'value' => $c->value,
                'label' => $this->getCompareWithLabel($c),
            ], ReportCompareWith::cases()),

            'presets' => $this->getPresets(),
        ]);
    }

    private function getTypeLabel(ReportType $type): string
    {
        return match ($type) {
            ReportType::Expenses => 'Expenses',
            ReportType::Income => 'Income',
            ReportType::ExpensesAndIncome => 'Expenses & Income',
            ReportType::Balance => 'Balance',
            ReportType::CashFlow => 'Cash Flow',
            ReportType::Budgets => 'Budgets',
        };
    }

    private function getGroupByLabel(ReportGroupBy $groupBy): string
    {
        return match ($groupBy) {
            ReportGroupBy::Categories => 'By Categories',
            ReportGroupBy::Days => 'By Days',
            ReportGroupBy::Weeks => 'By Weeks',
            ReportGroupBy::Months => 'By Months',
            ReportGroupBy::Accounts => 'By Accounts',
            ReportGroupBy::Tags => 'By Tags',
            ReportGroupBy::None => 'No Grouping',
        };
    }

    private function getMetricLabel(ReportMetric $metric): string
    {
        return match ($metric) {
            ReportMetric::Sum => 'Sum',
            ReportMetric::Count => 'Transaction Count',
            ReportMetric::Average => 'Average',
            ReportMetric::Min => 'Minimum',
            ReportMetric::Max => 'Maximum',
            ReportMetric::Median => 'Median',
            ReportMetric::PercentOfTotal => '% of Total',
            ReportMetric::PercentOfIncome => '% of Income',
        };
    }

    private function getCompareWithLabel(ReportCompareWith $compareWith): string
    {
        return match ($compareWith) {
            ReportCompareWith::PreviousPeriod => 'Previous Period',
            ReportCompareWith::SamePeriodLastYear => 'Same Period Last Year',
            ReportCompareWith::Budget => 'Budget',
        };
    }

    private function getPresets(): array
    {
        $now = now();

        return [
            [
                'label' => 'This Week',
                'start_date' => $now->copy()->startOfWeek()->toDateString(),
                'end_date' => $now->copy()->endOfWeek()->toDateString(),
            ],
            [
                'label' => 'Last Week',
                'start_date' => $now->copy()->subWeek()->startOfWeek()->toDateString(),
                'end_date' => $now->copy()->subWeek()->endOfWeek()->toDateString(),
            ],
            [
                'label' => 'This Month',
                'start_date' => $now->copy()->startOfMonth()->toDateString(),
                'end_date' => $now->copy()->endOfMonth()->toDateString(),
            ],
            [
                'label' => 'Last Month',
                'start_date' => $now->copy()->subMonth()->startOfMonth()->toDateString(),
                'end_date' => $now->copy()->subMonth()->endOfMonth()->toDateString(),
            ],
            [
                'label' => 'This Quarter',
                'start_date' => $now->copy()->startOfQuarter()->toDateString(),
                'end_date' => $now->copy()->endOfQuarter()->toDateString(),
            ],
            [
                'label' => 'Last Quarter',
                'start_date' => $now->copy()->subQuarter()->startOfQuarter()->toDateString(),
                'end_date' => $now->copy()->subQuarter()->endOfQuarter()->toDateString(),
            ],
            [
                'label' => 'This Year',
                'start_date' => $now->copy()->startOfYear()->toDateString(),
                'end_date' => $now->copy()->endOfYear()->toDateString(),
            ],
            [
                'label' => 'Last Year',
                'start_date' => $now->copy()->subYear()->startOfYear()->toDateString(),
                'end_date' => $now->copy()->subYear()->endOfYear()->toDateString(),
            ],
            [
                'label' => 'Last 30 Days',
                'start_date' => $now->copy()->subDays(29)->toDateString(),
                'end_date' => $now->toDateString(),
            ],
            [
                'label' => 'Last 90 Days',
                'start_date' => $now->copy()->subDays(89)->toDateString(),
                'end_date' => $now->toDateString(),
            ],
            [
                'label' => 'Last 12 Months',
                'start_date' => $now->copy()->subMonths(11)->startOfMonth()->toDateString(),
                'end_date' => $now->copy()->endOfMonth()->toDateString(),
            ],
        ];
    }
}
