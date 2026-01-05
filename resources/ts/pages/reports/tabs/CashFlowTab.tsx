import { MetricCard } from '../components/MetricCard'
import { CashFlowChart } from '../components/CashFlowChart'
import { useOverviewMetrics } from '@/hooks'
import { Skeleton } from '@/components/ui/skeleton'
import type { ReportFilters } from '../types'

interface CashFlowTabProps {
    filters: ReportFilters
}

export function CashFlowTab({ filters }: CashFlowTabProps) {
    const { data, isLoading, error } = useOverviewMetrics(filters)

    if (error) {
        return (
            <div className="text-center py-8 text-red-500">
                Failed to load cash flow metrics
            </div>
        )
    }

    return (
        <div className="space-y-6">
            {/* Cash Flow Metrics - 3 cards in a row */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {isLoading ? (
                    <>
                        <Skeleton className="h-[140px]" />
                        <Skeleton className="h-[140px]" />
                        <Skeleton className="h-[140px]" />
                    </>
                ) : data ? (
                    <>
                        <MetricCard
                            title="Inflow"
                            value={data.income.value}
                            previousValue={data.income.previous}
                            sparklineData={data.income.sparkline}
                            type="income"
                            compareWith={filters.compareWith}
                            currency={data.currency}
                        />
                        <MetricCard
                            title="Outflow"
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
                    </>
                ) : null}
            </div>

            {/* Cash Flow Chart */}
            <CashFlowChart filters={filters} />
        </div>
    )
}
