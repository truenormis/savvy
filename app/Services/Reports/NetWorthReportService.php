<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\Models\Account;
use App\Models\Currency;
use App\Repositories\AccountBalanceRepository;
use Carbon\Carbon;

class NetWorthReportService
{
    public function __construct(
        private AccountBalanceRepository $accountBalanceRepository,
        private PeriodGenerator $periodGenerator
    ) {}

    public function getCurrent(ReportFilterData $filters): array
    {
        $dateRange = $filters->getDateRange();
        $comparisonRange = $filters->getComparisonDateRange();

        $current = $this->getNetWorthAtDate($dateRange['end'], $filters);
        $currentTotal = array_sum(array_column($current, 'balance'));

        $previousTotal = null;
        if ($comparisonRange) {
            $previous = $this->getNetWorthAtDate($comparisonRange['end'], $filters);
            $previousTotal = array_sum(array_column($previous, 'balance'));
        }

        $change = $previousTotal !== null ? $currentTotal - $previousTotal : 0;
        $changePercent = $previousTotal && $previousTotal != 0
            ? round(($change / abs($previousTotal)) * 100, 1)
            : 0;

        $accounts = [];
        foreach ($current as $account) {
            $accounts[] = [
                'id' => $account['id'],
                'name' => $account['name'],
                'type' => $account['type'],
                'balance' => round($account['balance'], 2),
                'percentage' => $currentTotal > 0
                    ? round(($account['balance'] / $currentTotal) * 100, 1)
                    : 0,
            ];
        }

        usort($accounts, fn($a, $b) => $b['balance'] <=> $a['balance']);

        return [
            'current' => round($currentTotal, 2),
            'previous' => $previousTotal !== null ? round($previousTotal, 2) : null,
            'change' => round($change, 2),
            'changePercent' => $changePercent,
            'accounts' => $accounts,
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    public function getHistory(ReportFilterData $filters, string $groupBy = 'day'): array
    {
        $dateRange = $filters->getDateRange();
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        $labels = [];
        $data = [];

        $current = $start->copy();
        while ($current->lte($end)) {
            $periodEnd = $this->periodGenerator->getPeriodEnd($current, $end, $groupBy);
            $labels[] = $this->periodGenerator->getPeriodLabel($current, $groupBy);

            $accounts = $this->getNetWorthAtDate($periodEnd, $filters);
            $data[] = round(array_sum(array_column($accounts, 'balance')), 2);

            $current = $this->periodGenerator->getNextPeriod($current, $groupBy);
        }

        return [
            'labels' => $labels,
            'values' => $data,
            'currency' => Currency::getBase()?->symbol ?? '$',
        ];
    }

    private function getNetWorthAtDate(Carbon $date, ReportFilterData $filters): array
    {
        $accountsQuery = Account::query()
            ->where('is_active', true)
            ->regularAccounts()
            ->with('currency');

        if (!empty($filters->accountIds)) {
            $accountsQuery->whereIn('id', $filters->accountIds);
        }

        $accounts = $accountsQuery->get();

        return $this->accountBalanceRepository->getBalancesAtDate($accounts, $date->toDateString());
    }
}
