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

        return match ($this->periodType) {
            'month' => $this->wholeCalendarShift($start, fn (Carbon $d) => $d->subMonthNoOverflow(), 'startOfMonth', 'endOfMonth'),
            'quarter' => $this->wholeCalendarShift($start, fn (Carbon $d) => $d->subQuarterNoOverflow(), 'startOfQuarter', 'endOfQuarter'),
            'year' => $this->wholeCalendarShift($start, fn (Carbon $d) => $d->subYearNoOverflow(), 'startOfYear', 'endOfYear'),
            'ytd' => [
                'start' => $start->copy()->subYearNoOverflow()->startOfYear(),
                'end' => $end->copy()->subYearNoOverflow()->endOfDay(),
            ],
            default => $this->getAdjacentRange($start, $end),
        };
    }

    private function wholeCalendarShift(Carbon $start, callable $shift, string $startOf, string $endOf): array
    {
        $anchor = $shift($start->copy());

        return [
            'start' => $anchor->copy()->{$startOf}(),
            'end' => $anchor->copy()->{$endOf}(),
        ];
    }

    private function getAdjacentRange(Carbon $start, Carbon $end): array
    {
        $lengthDays = (int) round($start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay())) + 1;
        $prevEnd = $start->copy()->subDay()->endOfDay();

        return [
            'start' => $prevEnd->copy()->subDays($lengthDays - 1)->startOfDay(),
            'end' => $prevEnd,
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
        $rangeDays = (int) round($currentRange['start']->copy()->startOfDay()->diffInDays($currentRange['end']->copy()->startOfDay())) + 1;

        for ($i = $count - 1; $i >= 0; $i--) {
            $periods[] = match ($this->periodType) {
                'quarter' => $this->wholeCalendarShift(
                    $currentRange['start']->copy()->subQuartersNoOverflow($i),
                    fn (Carbon $d) => $d, 'startOfQuarter', 'endOfQuarter'
                ),
                'year' => $this->wholeCalendarShift(
                    $currentRange['start']->copy()->subYearsNoOverflow($i),
                    fn (Carbon $d) => $d, 'startOfYear', 'endOfYear'
                ),
                'custom' => [
                    'start' => $currentRange['start']->copy()->subDays($rangeDays * $i)->startOfDay(),
                    'end' => $currentRange['end']->copy()->subDays($rangeDays * $i)->endOfDay(),
                ],
                'ytd' => $this->wholeCalendarShift(
                    $currentRange['end']->copy()->subMonthsNoOverflow($i),
                    fn (Carbon $d) => $d, 'startOfMonth', 'endOfMonth'
                ),
                default => $this->wholeCalendarShift(
                    $currentRange['start']->copy()->subMonthsNoOverflow($i),
                    fn (Carbon $d) => $d, 'startOfMonth', 'endOfMonth'
                ),
            };
        }

        return $periods;
    }
}
