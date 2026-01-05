<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class ReportFilterData
{
    public function __construct(
        public string $periodType = 'month',       // month|quarter|year|ytd|custom
        public ?string $periodValue = null,        // 2024-01 | 2024-Q1 | 2024
        public ?string $startDate = null,          // for custom period
        public ?string $endDate = null,
        public string $compareWith = 'none',       // none|previous_period|same_period_last_year
        public array $accountIds = [],
        public array $categoryIds = [],
        public array $tagIds = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            periodType: $data['period_type'] ?? 'month',
            periodValue: $data['period_value'] ?? null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            compareWith: $data['compare_with'] ?? 'none',
            accountIds: $data['account_ids'] ?? [],
            categoryIds: $data['category_ids'] ?? [],
            tagIds: $data['tag_ids'] ?? [],
        );
    }

    /**
     * Calculate start and end dates based on period type and value
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getDateRange(): array
    {
        return match ($this->periodType) {
            'month' => $this->getMonthRange(),
            'quarter' => $this->getQuarterRange(),
            'year' => $this->getYearRange(),
            'ytd' => $this->getYtdRange(),
            'custom' => $this->getCustomRange(),
            default => $this->getMonthRange(),
        };
    }

    /**
     * Calculate comparison period date range
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function getComparisonDateRange(): ?array
    {
        if ($this->compareWith === 'none') {
            return null;
        }

        $currentRange = $this->getDateRange();

        return match ($this->compareWith) {
            'previous_period' => $this->getPreviousPeriodRange($currentRange),
            'same_period_last_year' => $this->getSamePeriodLastYearRange($currentRange),
            default => null,
        };
    }

    private function getMonthRange(): array
    {
        if ($this->periodValue && preg_match('/^(\d{4})-(\d{2})$/', $this->periodValue, $matches)) {
            $date = Carbon::createFromDate((int) $matches[1], (int) $matches[2], 1);
        } else {
            $date = Carbon::now();
        }

        return [
            'start' => $date->copy()->startOfMonth(),
            'end' => $date->copy()->endOfMonth(),
        ];
    }

    private function getQuarterRange(): array
    {
        if ($this->periodValue && preg_match('/^(\d{4})-Q([1-4])$/', $this->periodValue, $matches)) {
            $year = (int) $matches[1];
            $quarter = (int) $matches[2];
            $month = ($quarter - 1) * 3 + 1;
            $date = Carbon::createFromDate($year, $month, 1);
        } else {
            $date = Carbon::now();
        }

        return [
            'start' => $date->copy()->startOfQuarter(),
            'end' => $date->copy()->endOfQuarter(),
        ];
    }

    private function getYearRange(): array
    {
        if ($this->periodValue && preg_match('/^(\d{4})$/', $this->periodValue, $matches)) {
            $date = Carbon::createFromDate((int) $matches[1], 1, 1);
        } else {
            $date = Carbon::now();
        }

        return [
            'start' => $date->copy()->startOfYear(),
            'end' => $date->copy()->endOfYear(),
        ];
    }

    private function getYtdRange(): array
    {
        $now = Carbon::now();

        return [
            'start' => $now->copy()->startOfYear(),
            'end' => $now->copy()->endOfDay(),
        ];
    }

    private function getCustomRange(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate) : Carbon::now()->startOfMonth();
        $end = $this->endDate ? Carbon::parse($this->endDate) : Carbon::now()->endOfMonth();

        return [
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
        ];
    }

    private function getPreviousPeriodRange(array $currentRange): array
    {
        $start = $currentRange['start'];
        $end = $currentRange['end'];
        $daysDiff = $start->diffInDays($end) + 1;

        return [
            'start' => $start->copy()->subDays($daysDiff),
            'end' => $end->copy()->subDays($daysDiff),
        ];
    }

    private function getSamePeriodLastYearRange(array $currentRange): array
    {
        return [
            'start' => $currentRange['start']->copy()->subYear(),
            'end' => $currentRange['end']->copy()->subYear(),
        ];
    }

    /**
     * Get sparkline periods (6 previous periods of same type)
     *
     * @return array<array{start: Carbon, end: Carbon}>
     */
    public function getSparklinePeriods(int $count = 6): array
    {
        $periods = [];
        $currentRange = $this->getDateRange();

        for ($i = $count - 1; $i >= 0; $i--) {
            $periods[] = match ($this->periodType) {
                'month' => [
                    'start' => $currentRange['start']->copy()->subMonths($i),
                    'end' => $currentRange['end']->copy()->subMonths($i),
                ],
                'quarter' => [
                    'start' => $currentRange['start']->copy()->subQuarters($i),
                    'end' => $currentRange['end']->copy()->subQuarters($i),
                ],
                'year' => [
                    'start' => $currentRange['start']->copy()->subYears($i),
                    'end' => $currentRange['end']->copy()->subYears($i),
                ],
                default => [
                    'start' => $currentRange['start']->copy()->subMonths($i),
                    'end' => $currentRange['end']->copy()->subMonths($i),
                ],
            };
        }

        return $periods;
    }
}
