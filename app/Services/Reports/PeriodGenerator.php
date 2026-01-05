<?php

namespace App\Services\Reports;

use Carbon\Carbon;

class PeriodGenerator
{
    public function generate(Carbon $startDate, Carbon $endDate, string $groupBy): array
    {
        $periods = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            switch ($groupBy) {
                case 'day':
                    $periods[] = [
                        'key' => $current->toDateString(),
                        'label' => $current->format('M j'),
                    ];
                    $current->addDay();
                    break;

                case 'week':
                    $weekStart = $current->copy()->startOfWeek(Carbon::SUNDAY);
                    $periods[] = [
                        'key' => $weekStart->toDateString(),
                        'label' => 'Week ' . $weekStart->format('M j'),
                    ];
                    $current->addWeek();
                    break;

                case 'month':
                    $periods[] = [
                        'key' => $current->copy()->startOfMonth()->toDateString(),
                        'label' => $current->format("M 'y"),
                    ];
                    $current->addMonth();
                    break;
            }
        }

        return $periods;
    }

    public function getSqlFormat(string $groupBy): string
    {
        return match ($groupBy) {
            'day' => "DATE(transactions.date)",
            'week' => "DATE(transactions.date, '-' || strftime('%w', transactions.date) || ' days')",
            'month' => "DATE(transactions.date, 'start of month')",
        };
    }

    public function getNextPeriod(Carbon $current, string $groupBy): Carbon
    {
        return match ($groupBy) {
            'week' => $current->copy()->addWeek()->startOfWeek(),
            'month' => $current->copy()->addMonth()->startOfMonth(),
            default => $current->copy()->addDay(),
        };
    }

    public function getPeriodEnd(Carbon $current, Carbon $maxEnd, string $groupBy): Carbon
    {
        return match ($groupBy) {
            'week' => $current->copy()->endOfWeek()->min($maxEnd),
            'month' => $current->copy()->endOfMonth()->min($maxEnd),
            default => $current->copy(),
        };
    }

    public function getPeriodLabel(Carbon $current, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => 'W' . $current->weekOfYear . ' ' . $current->format("M 'y"),
            'month' => $current->format("M 'y"),
            default => $current->format('M j'),
        };
    }
}
