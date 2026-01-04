<?php

namespace App\Services;

use App\DTOs\ReportConfigData;
use App\Enums\ReportCompareWith;
use App\Enums\ReportGroupBy;
use App\Enums\ReportMetric;
use App\Enums\ReportType;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Currency;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function generate(ReportConfigData $config): array
    {
        $data = $this->getReportData($config);
        $result = [
            'type' => $config->type->value,
            'period' => [
                'start' => $config->startDate->toDateString(),
                'end' => $config->endDate->toDateString(),
            ],
            'group_by' => $config->groupBy->value,
            'metrics' => array_map(fn($m) => $m->value, $config->metrics),
            'data' => $data,
            'totals' => $this->calculateTotals($data, $config),
        ];

        // Add comparison if requested
        if ($config->compareWith && $config->compareWith !== ReportCompareWith::Budget) {
            $result['comparison'] = $this->getComparisonData($config);
        }

        if ($config->compareWith === ReportCompareWith::Budget) {
            $result['budget_comparison'] = $this->getBudgetComparison($config);
        }

        return $result;
    }

    private function getReportData(ReportConfigData $config): array
    {
        return match ($config->type) {
            ReportType::Expenses => $this->getTransactionReport($config, [TransactionType::Expense]),
            ReportType::Income => $this->getTransactionReport($config, [TransactionType::Income]),
            ReportType::ExpensesAndIncome => $this->getTransactionReport($config, [TransactionType::Expense, TransactionType::Income]),
            ReportType::Balance => $this->getBalanceReport($config),
            ReportType::CashFlow => $this->getCashFlowReport($config),
            ReportType::Budgets => $this->getBudgetsReport($config),
        };
    }

    private function getTransactionReport(ReportConfigData $config, array $types): array
    {
        $query = $this->buildBaseQuery($config, $types);

        if ($config->groupBy === ReportGroupBy::None) {
            return $this->aggregateTransactions($query->get(), $config);
        }

        $grouped = $this->groupTransactions($query->get(), $config);

        return $this->formatGroupedData($grouped, $config);
    }

    private function buildBaseQuery(ReportConfigData $config, array $types)
    {
        // Convert enums to string values for the query
        $typeValues = array_map(fn($t) => $t->value, $types);

        $query = Transaction::query()
            ->with(['account.currency', 'category', 'tags'])
            ->whereIn('type', $typeValues)
            ->whereBetween('date', [$config->startDate->toDateString(), $config->endDate->toDateString()]);

        if ($config->categoryIds) {
            $query->whereIn('category_id', $config->categoryIds);
        }

        if ($config->accountIds) {
            $query->whereIn('account_id', $config->accountIds);
        }

        if ($config->tagIds) {
            $query->whereHas('tags', function ($q) use ($config) {
                $q->whereIn('tags.id', $config->tagIds);
            });
        }

        return $query;
    }

    private function groupTransactions(Collection $transactions, ReportConfigData $config): Collection
    {
        $grouper = match ($config->groupBy) {
            ReportGroupBy::Categories => fn($t) => $t->category_id ?? 0,
            ReportGroupBy::Accounts => fn($t) => $t->account_id,
            ReportGroupBy::Tags => fn($t) => $t->tags->pluck('id')->first() ?? 0,
            ReportGroupBy::Days => fn($t) => Carbon::parse($t->date)->format('Y-m-d'),
            ReportGroupBy::Weeks => fn($t) => Carbon::parse($t->date)->startOfWeek()->format('Y-m-d'),
            ReportGroupBy::Months => fn($t) => Carbon::parse($t->date)->format('Y-m'),
            default => fn($t) => 'all',
        };

        // For tags, we need to handle transactions with multiple tags
        if ($config->groupBy === ReportGroupBy::Tags) {
            $expanded = collect();
            foreach ($transactions as $transaction) {
                if ($transaction->tags->isEmpty()) {
                    $expanded->push((object) array_merge($transaction->toArray(), [
                        '_group_key' => 0,
                        '_tag_name' => 'Untagged',
                        '_original' => $transaction,
                    ]));
                } else {
                    foreach ($transaction->tags as $tag) {
                        $expanded->push((object) array_merge($transaction->toArray(), [
                            '_group_key' => $tag->id,
                            '_tag_name' => $tag->name,
                            '_original' => $transaction,
                        ]));
                    }
                }
            }
            return $expanded->groupBy('_group_key');
        }

        return $transactions->groupBy($grouper);
    }

    private function formatGroupedData(Collection $grouped, ReportConfigData $config): array
    {
        $result = [];
        $baseCurrency = Currency::getBase();

        foreach ($grouped as $key => $transactions) {
            $label = $this->getGroupLabel($key, $config->groupBy, $transactions->first());

            $metrics = $this->calculateMetrics($transactions, $config, $baseCurrency);

            $item = [
                'key' => $key,
                'label' => $label,
                'metrics' => $metrics,
            ];

            // Handle sub-grouping
            if ($config->subGroupBy) {
                $subGrouped = $this->groupTransactions(
                    $transactions instanceof Collection ? $transactions : collect($transactions),
                    new ReportConfigData(
                        type: $config->type,
                        startDate: $config->startDate,
                        endDate: $config->endDate,
                        groupBy: $config->subGroupBy,
                        metrics: $config->metrics,
                    )
                );
                $item['children'] = $this->formatGroupedData($subGrouped, new ReportConfigData(
                    type: $config->type,
                    startDate: $config->startDate,
                    endDate: $config->endDate,
                    groupBy: $config->subGroupBy,
                    metrics: $config->metrics,
                ));
            }

            $result[] = $item;
        }

        // Sort by sum descending
        usort($result, fn($a, $b) => ($b['metrics']['sum'] ?? 0) <=> ($a['metrics']['sum'] ?? 0));

        return $result;
    }

    private function getGroupLabel($key, ReportGroupBy $groupBy, $sample): string
    {
        return match ($groupBy) {
            ReportGroupBy::Categories => $sample->category?->name ?? 'Uncategorized',
            ReportGroupBy::Accounts => $sample->account?->name ?? 'Unknown Account',
            ReportGroupBy::Tags => $sample->_tag_name ?? 'Untagged',
            ReportGroupBy::Days => Carbon::parse($key)->format('d M Y'),
            ReportGroupBy::Weeks => 'Week of ' . Carbon::parse($key)->format('d M Y'),
            ReportGroupBy::Months => Carbon::parse($key . '-01')->format('F Y'),
            default => 'Total',
        };
    }

    private function calculateMetrics(Collection $transactions, ReportConfigData $config, ?Currency $baseCurrency): array
    {
        $amounts = $transactions->map(function ($t) use ($baseCurrency) {
            $transaction = $t->_original ?? $t;
            $currency = $transaction->account->currency ?? $baseCurrency;
            return $currency ? $currency->convertToBase((float) ($t->amount ?? $transaction->amount)) : (float) ($t->amount ?? $transaction->amount);
        });

        $total = $amounts->sum();
        $count = $amounts->count();

        $result = [];

        foreach ($config->metrics as $metric) {
            $result[$metric->value] = match ($metric) {
                ReportMetric::Sum => round($total, 2),
                ReportMetric::Count => $count,
                ReportMetric::Average => $count > 0 ? round($total / $count, 2) : 0,
                ReportMetric::Min => $count > 0 ? round($amounts->min(), 2) : 0,
                ReportMetric::Max => $count > 0 ? round($amounts->max(), 2) : 0,
                ReportMetric::Median => $count > 0 ? round($this->calculateMedian($amounts), 2) : 0,
                ReportMetric::PercentOfTotal => 0, // Calculated later
                ReportMetric::PercentOfIncome => 0, // Calculated later
            };
        }

        return $result;
    }

    private function calculateMedian(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0;
        }

        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($sorted[$middle - 1] + $sorted[$middle]) / 2;
        }

        return $sorted[$middle];
    }

    private function aggregateTransactions(Collection $transactions, ReportConfigData $config): array
    {
        $baseCurrency = Currency::getBase();
        $metrics = $this->calculateMetrics($transactions, $config, $baseCurrency);

        return [[
            'key' => 'total',
            'label' => 'Total',
            'metrics' => $metrics,
        ]];
    }

    private function calculateTotals(array $data, ReportConfigData $config): array
    {
        // Handle special report types with different metric structures
        if ($config->type === ReportType::Balance) {
            $totalIncome = array_sum(array_column(array_column($data, 'metrics'), 'income'));
            $totalExpense = array_sum(array_column(array_column($data, 'metrics'), 'expense'));
            $totalCount = array_sum(array_column(array_column($data, 'metrics'), 'count'));
            return [
                'income' => round($totalIncome, 2),
                'expense' => round($totalExpense, 2),
                'net' => round($totalIncome - $totalExpense, 2),
                'balance' => !empty($data) ? ($data[array_key_last($data)]['metrics']['balance'] ?? 0) : 0,
                'sum' => round($totalIncome - $totalExpense, 2),
                'count' => $totalCount,
            ];
        }

        if ($config->type === ReportType::Budgets) {
            $totalBudget = array_sum(array_column(array_column($data, 'metrics'), 'budget_amount'));
            $totalSpent = array_sum(array_column(array_column($data, 'metrics'), 'spent'));
            return [
                'budget_amount' => round($totalBudget, 2),
                'spent' => round($totalSpent, 2),
                'remaining' => round($totalBudget - $totalSpent, 2),
                'percent' => $totalBudget > 0 ? round($totalSpent / $totalBudget * 100, 2) : 0,
                'sum' => round($totalSpent, 2),
                'count' => 0,
            ];
        }

        if ($config->type === ReportType::CashFlow) {
            $totalIncome = array_sum(array_column(array_column($data, 'metrics'), 'income'));
            $totalExpense = array_sum(array_column(array_column($data, 'metrics'), 'expense'));
            $totalCount = array_sum(array_column(array_column($data, 'metrics'), 'count'));
            return [
                'income' => round($totalIncome, 2),
                'expense' => round($totalExpense, 2),
                'net' => round($totalIncome - $totalExpense, 2),
                'sum' => round($totalIncome - $totalExpense, 2),
                'count' => $totalCount,
            ];
        }

        $totals = [];

        foreach ($config->metrics as $metric) {
            $totals[$metric->value] = match ($metric) {
                ReportMetric::Sum => array_sum(array_column(array_column($data, 'metrics'), 'sum')),
                ReportMetric::Count => array_sum(array_column(array_column($data, 'metrics'), 'count')),
                ReportMetric::Average, ReportMetric::Min, ReportMetric::Max, ReportMetric::Median => null,
                ReportMetric::PercentOfTotal => 100,
                ReportMetric::PercentOfIncome => null,
            };
        }

        // Calculate percentages
        $totalSum = $totals[ReportMetric::Sum->value] ?? array_sum(array_column(array_column($data, 'metrics'), 'sum'));

        if ($totalSum > 0 && in_array(ReportMetric::PercentOfTotal, $config->metrics)) {
            foreach ($data as &$item) {
                $item['metrics']['percent_of_total'] = round(($item['metrics']['sum'] ?? 0) / $totalSum * 100, 2);
            }
        }

        return $totals;
    }

    private function getBalanceReport(ReportConfigData $config): array
    {
        $query = $this->buildBaseQuery($config, [TransactionType::Income, TransactionType::Expense]);
        $transactions = $query->orderBy('date')->get();

        $runningBalance = 0;
        $dataPoints = [];

        $grouper = match ($config->groupBy) {
            ReportGroupBy::Days => fn($t) => Carbon::parse($t->date)->format('Y-m-d'),
            ReportGroupBy::Weeks => fn($t) => Carbon::parse($t->date)->startOfWeek()->format('Y-m-d'),
            ReportGroupBy::Months => fn($t) => Carbon::parse($t->date)->format('Y-m'),
            default => fn($t) => Carbon::parse($t->date)->format('Y-m-d'),
        };

        $grouped = $transactions->groupBy($grouper);

        foreach ($grouped as $key => $dayTransactions) {
            $dayIncome = 0;
            $dayExpense = 0;

            foreach ($dayTransactions as $t) {
                $amount = $t->account->currency->convertToBase((float) $t->amount);

                // Compare using string values to handle both casted and uncasted scenarios
                $typeValue = $t->type instanceof TransactionType ? $t->type->value : $t->type;

                if ($typeValue === TransactionType::Income->value) {
                    $dayIncome += $amount;
                } else {
                    $dayExpense += $amount;
                }
            }

            $runningBalance += $dayIncome - $dayExpense;

            $dataPoints[] = [
                'key' => $key,
                'label' => $this->getGroupLabel($key, $config->groupBy, $dayTransactions->first()),
                'metrics' => [
                    'income' => round($dayIncome, 2),
                    'expense' => round($dayExpense, 2),
                    'net' => round($dayIncome - $dayExpense, 2),
                    'balance' => round($runningBalance, 2),
                    // Standard metrics
                    'sum' => round($dayIncome - $dayExpense, 2),
                    'count' => $dayTransactions->count(),
                ],
            ];
        }

        return $dataPoints;
    }

    private function getCashFlowReport(ReportConfigData $config): array
    {
        $incomeConfig = new ReportConfigData(
            type: ReportType::Income,
            startDate: $config->startDate,
            endDate: $config->endDate,
            groupBy: $config->groupBy,
            metrics: $config->metrics,
            categoryIds: $config->categoryIds,
            accountIds: $config->accountIds,
            tagIds: $config->tagIds,
        );

        $expenseConfig = new ReportConfigData(
            type: ReportType::Expenses,
            startDate: $config->startDate,
            endDate: $config->endDate,
            groupBy: $config->groupBy,
            metrics: $config->metrics,
            categoryIds: $config->categoryIds,
            accountIds: $config->accountIds,
            tagIds: $config->tagIds,
        );

        $income = $this->getTransactionReport($incomeConfig, [TransactionType::Income]);
        $expenses = $this->getTransactionReport($expenseConfig, [TransactionType::Expense]);

        // Merge by key
        $merged = [];
        $allKeys = array_unique(array_merge(
            array_column($income, 'key'),
            array_column($expenses, 'key')
        ));

        foreach ($allKeys as $key) {
            $incomeItem = collect($income)->firstWhere('key', $key);
            $expenseItem = collect($expenses)->firstWhere('key', $key);

            $incomeMetrics = $incomeItem['metrics'] ?? [];
            $expenseMetrics = $expenseItem['metrics'] ?? [];

            $incomeSum = $incomeMetrics['sum'] ?? 0;
            $expenseSum = $expenseMetrics['sum'] ?? 0;

            // Merge standard metrics from both income and expenses
            $metrics = [
                'income' => $incomeSum,
                'expense' => $expenseSum,
                'net' => round($incomeSum - $expenseSum, 2),
            ];

            // Add standard metrics (combined from income and expense)
            foreach ($config->metrics as $metric) {
                $metricKey = $metric->value;
                if (!isset($metrics[$metricKey])) {
                    $incomeVal = $incomeMetrics[$metricKey] ?? 0;
                    $expenseVal = $expenseMetrics[$metricKey] ?? 0;

                    $metrics[$metricKey] = match ($metric) {
                        ReportMetric::Sum => round($incomeVal - $expenseVal, 2), // Net sum
                        ReportMetric::Count => $incomeVal + $expenseVal,
                        ReportMetric::Average, ReportMetric::Min, ReportMetric::Max, ReportMetric::Median =>
                            $incomeVal > 0 ? $incomeVal : $expenseVal,
                        default => 0,
                    };
                }
            }

            $merged[] = [
                'key' => $key,
                'label' => $incomeItem['label'] ?? $expenseItem['label'] ?? $key,
                'metrics' => $metrics,
            ];
        }

        return $merged;
    }

    private function getBudgetsReport(ReportConfigData $config): array
    {
        $budgets = Budget::with(['categories', 'tags', 'currency'])
            ->where('is_active', true)
            ->get();

        $result = [];
        $budgetService = app(BudgetService::class);
        $baseCurrency = Currency::getBase();

        foreach ($budgets as $budget) {
            $progress = $budgetService->getProgress($budget, $config->startDate, $config->endDate);

            // Convert from budget's currency to base currency
            $budgetCurrency = $budget->currency ?? $baseCurrency;
            $rate = $budgetCurrency ? (float) $budgetCurrency->rate : 1;

            // Values from progress are in budget's currency, convert to base
            $budgetAmountBase = (float) $budget->amount * $rate;
            $spentBase = $progress['spent'] * $rate;
            $remainingBase = max(0, $budgetAmountBase - $spentBase);

            $result[] = [
                'key' => $budget->id,
                'label' => $budget->name,
                'metrics' => [
                    'budget_amount' => round($budgetAmountBase, 2),
                    'spent' => round($spentBase, 2),
                    'remaining' => round($remainingBase, 2),
                    'percent' => $progress['percent'],
                    'is_exceeded' => $progress['is_exceeded'],
                    // Standard metrics mapped to budget values
                    'sum' => round($spentBase, 2),
                    'count' => 0,
                ],
            ];
        }

        return $result;
    }

    private function getComparisonData(ReportConfigData $config): ?array
    {
        $comparisonPeriod = $config->getComparisonPeriod();

        if (!$comparisonPeriod) {
            return null;
        }

        $comparisonConfig = new ReportConfigData(
            type: $config->type,
            startDate: $comparisonPeriod['start'],
            endDate: $comparisonPeriod['end'],
            groupBy: $config->groupBy,
            subGroupBy: $config->subGroupBy,
            metrics: $config->metrics,
            categoryIds: $config->categoryIds,
            accountIds: $config->accountIds,
            tagIds: $config->tagIds,
        );

        $comparisonData = $this->getReportData($comparisonConfig);

        return [
            'period' => [
                'start' => $comparisonPeriod['start']->toDateString(),
                'end' => $comparisonPeriod['end']->toDateString(),
            ],
            'data' => $comparisonData,
            'totals' => $this->calculateTotals($comparisonData, $comparisonConfig),
        ];
    }

    private function getBudgetComparison(ReportConfigData $config): array
    {
        $budgets = Budget::with(['categories', 'tags'])
            ->where('is_active', true)
            ->get();

        $expenses = Transaction::query()
            ->with(['account.currency', 'category'])
            ->where('type', TransactionType::Expense->value)
            ->whereBetween('date', [$config->startDate, $config->endDate])
            ->get();

        $baseCurrency = Currency::getBase();
        $result = [];

        foreach ($budgets as $budget) {
            $budgetExpenses = $expenses->filter(function ($t) use ($budget) {
                if ($budget->is_global) {
                    return true;
                }

                $categoryMatch = $budget->categories->isEmpty() ||
                    $budget->categories->contains('id', $t->category_id);

                $tagMatch = $budget->tags->isEmpty() ||
                    $t->tags->intersect($budget->tags)->isNotEmpty();

                return $categoryMatch && $tagMatch;
            });

            $spent = $budgetExpenses->sum(function ($t) use ($baseCurrency) {
                return $t->account->currency->convertToBase((float) $t->amount);
            });

            $result[] = [
                'budget_id' => $budget->id,
                'budget_name' => $budget->name,
                'budget_amount' => (float) $budget->amount,
                'spent' => round($spent, 2),
                'remaining' => round($budget->amount - $spent, 2),
                'percent' => $budget->amount > 0 ? round($spent / $budget->amount * 100, 2) : 0,
                'is_exceeded' => $spent > $budget->amount,
            ];
        }

        return $result;
    }
}
