<?php

namespace App\DTOs;

use App\Enums\ReportCompareWith;
use App\Enums\ReportGroupBy;
use App\Enums\ReportMetric;
use App\Enums\ReportType;
use Carbon\Carbon;

readonly class ReportConfigData
{
    public function __construct(
        public ReportType $type,
        public Carbon $startDate,
        public Carbon $endDate,
        public ReportGroupBy $groupBy = ReportGroupBy::None,
        public ?ReportGroupBy $subGroupBy = null,
        public array $metrics = [ReportMetric::Sum],
        public ?array $categoryIds = null,
        public ?array $accountIds = null,
        public ?array $tagIds = null,
        public ?ReportCompareWith $compareWith = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $metrics = array_map(
            fn($m) => ReportMetric::from($m),
            $data['metrics'] ?? ['sum']
        );

        return new self(
            type: ReportType::from($data['type']),
            startDate: Carbon::parse($data['start_date']),
            endDate: Carbon::parse($data['end_date']),
            groupBy: isset($data['group_by']) ? ReportGroupBy::from($data['group_by']) : ReportGroupBy::None,
            subGroupBy: isset($data['sub_group_by']) ? ReportGroupBy::from($data['sub_group_by']) : null,
            metrics: $metrics,
            categoryIds: $data['category_ids'] ?? null,
            accountIds: $data['account_ids'] ?? null,
            tagIds: $data['tag_ids'] ?? null,
            compareWith: isset($data['compare_with']) ? ReportCompareWith::from($data['compare_with']) : null,
        );
    }

    public function getComparisonPeriod(): ?array
    {
        if (!$this->compareWith) {
            return null;
        }

        $periodDays = $this->startDate->diffInDays($this->endDate) + 1;

        return match ($this->compareWith) {
            ReportCompareWith::PreviousPeriod => [
                'start' => $this->startDate->copy()->subDays($periodDays),
                'end' => $this->startDate->copy()->subDay(),
            ],
            ReportCompareWith::SamePeriodLastYear => [
                'start' => $this->startDate->copy()->subYear(),
                'end' => $this->endDate->copy()->subYear(),
            ],
            ReportCompareWith::Budget => null,
        };
    }
}
