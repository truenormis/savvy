import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { TrendingUp, TrendingDown, Calendar, CalendarDays } from 'lucide-react'
import { ExpensesStructureChart } from '../components/ExpensesStructureChart'
import { ExpensesDynamicsChart } from '../components/ExpensesDynamicsChart'
import { TopExpenses } from '../components/TopExpenses'
import { useTransactionReportSummary } from '@/hooks'
import type { ReportFilters } from '../types'

interface ExpensesTabProps {
    filters: ReportFilters
}

export function ExpensesTab({ filters }: ExpensesTabProps) {
    const { data, isLoading } = useTransactionReportSummary(filters, 'expense')

    const percentChange = data?.previous
        ? ((data.total - data.previous) / data.previous) * 100
        : 0
    const absoluteChange = data ? data.total - (data.previous || 0) : 0
    const isIncrease = percentChange > 0

    const formatCurrency = (val: number, currency: string = '$') => {
        return `${currency}${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
    }

    return (
        <div className="space-y-6">
            {/* Block 1 — Total Expenses */}
            <Card>
                <CardContent className="pt-6">
                    {isLoading ? (
                        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                            <div className="space-y-2">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-12 w-40" />
                                <Skeleton className="h-4 w-48" />
                            </div>
                            <div className="flex gap-6">
                                <Skeleton className="h-20 w-36" />
                                <Skeleton className="h-20 w-36" />
                            </div>
                        </div>
                    ) : data ? (
                        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                            {/* Main metric */}
                            <div className="space-y-2">
                                <p className="text-sm text-muted-foreground font-medium">
                                    Total Expenses
                                </p>
                                <p className="text-5xl font-bold text-red-600 tracking-tight">
                                    {formatCurrency(data.total, data.currency)}
                                </p>

                                {/* Comparison */}
                                {filters.compareWith !== 'none' && data.previous !== null && (
                                    <div className="flex items-center gap-3 pt-1">
                                        <span className={cn(
                                            'flex items-center gap-1 text-sm font-medium',
                                            isIncrease ? 'text-red-600' : 'text-green-600'
                                        )}>
                                            {isIncrease ? (
                                                <TrendingUp className="size-4" />
                                            ) : (
                                                <TrendingDown className="size-4" />
                                            )}
                                            {Math.abs(percentChange).toFixed(1)}%
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {isIncrease ? '+' : ''}{formatCurrency(absoluteChange, data.currency)} vs previous period
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Average stats */}
                            <div className="flex gap-6">
                                {/* Average per day */}
                                <div className="flex items-center gap-3 px-5 py-4 bg-muted/50 rounded-xl">
                                    <div className="flex items-center justify-center size-10 rounded-lg bg-orange-100 text-orange-600">
                                        <Calendar className="size-5" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Avg. per day</p>
                                        <p className="text-xl font-semibold">{formatCurrency(data.avgPerDay, data.currency)}</p>
                                        {filters.compareWith !== 'none' && data.prevAvgPerDay !== null && (
                                            <p className="text-xs text-muted-foreground">
                                                vs {formatCurrency(data.prevAvgPerDay, data.currency)}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Average per week */}
                                <div className="flex items-center gap-3 px-5 py-4 bg-muted/50 rounded-xl">
                                    <div className="flex items-center justify-center size-10 rounded-lg bg-purple-100 text-purple-600">
                                        <CalendarDays className="size-5" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Avg. per week</p>
                                        <p className="text-xl font-semibold">{formatCurrency(data.avgPerWeek, data.currency)}</p>
                                        {filters.compareWith !== 'none' && data.prevAvgPerWeek !== null && (
                                            <p className="text-xs text-muted-foreground">
                                                vs {formatCurrency(data.prevAvgPerWeek, data.currency)}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : null}
                </CardContent>
            </Card>

            {/* Block 2 — Expenses Structure */}
            <ExpensesStructureChart filters={filters} />

            {/* Block 4 — Expenses Dynamics */}
            <ExpensesDynamicsChart filters={filters} />

            {/* Block 5 — Top Expenses */}
            <TopExpenses filters={filters} />
        </div>
    )
}
