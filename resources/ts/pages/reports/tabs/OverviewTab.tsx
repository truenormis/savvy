import { MetricCard } from '../components/MetricCard'
import { SankeyDiagram } from '../components/SankeyDiagram'
import { ExpensePaceChart } from '../components/ExpensePaceChart'
import { ExpensesByCategory } from '../components/ExpensesByCategory'
import { ActivityHeatmap } from '../components/ActivityHeatmap'
import { useOverviewMetrics } from '@/hooks'
import { Skeleton } from '@/components/ui/skeleton'
import type { ReportFilters } from '../types'

interface OverviewTabProps {
    filters: ReportFilters
}

export function OverviewTab({ filters }: OverviewTabProps) {
    const { data, isLoading, error } = useOverviewMetrics(filters)

    if (error) {
        return (
            <div className="text-center py-8 text-red-500">
                Failed to load overview metrics
            </div>
        )
    }

    return (
        <div className="space-y-6">
            {/* Key Metrics - 4 cards in a row */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {isLoading ? (
                    <>
                        <Skeleton className="h-[140px]" />
                        <Skeleton className="h-[140px]" />
                        <Skeleton className="h-[140px]" />
                        <Skeleton className="h-[140px]" />
                    </>
                ) : data ? (
                    <>
                        <MetricCard
                            title="Income"
                            value={data.income.value}
                            previousValue={data.income.previous}
                            sparklineData={data.income.sparkline}
                            type="income"
                            compareWith={filters.compareWith}
                            currency={data.currency}
                        />
                        <MetricCard
                            title="Expenses"
                            value={data.expenses.value}
                            previousValue={data.expenses.previous}
                            sparklineData={data.expenses.sparkline}
                            type="expense"
                            compareWith={filters.compareWith}
                            currency={data.currency}
                        />
                        <MetricCard
                            title="Net Cash Flow"
                            value={data.netCashFlow.value}
                            previousValue={data.netCashFlow.previous}
                            sparklineData={data.netCashFlow.sparkline}
                            type="net"
                            compareWith={filters.compareWith}
                            currency={data.currency}
                        />
                        <MetricCard
                            title="Savings Rate"
                            value={data.savingsRate.value}
                            previousValue={data.savingsRate.previous}
                            sparklineData={data.savingsRate.sparkline}
                            type="percent"
                            compareWith={filters.compareWith}
                        />
                    </>
                ) : null}
            </div>

            {/* Sankey Diagram */}
            <SankeyDiagram filters={filters} />

            {/* Expense Pace Chart and Activity Heatmap */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <ExpensePaceChart filters={filters} />
                <ActivityHeatmap filters={filters} />
            </div>

            {/* Expenses by Category */}
            <ExpensesByCategory filters={filters} />
        </div>
    )
}
