import { useState, useMemo } from 'react'
import ReactECharts from 'echarts-for-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useCashFlowOverTime } from '@/hooks'
import type { ReportFilters } from '../types'
import type { CashFlowGroupBy } from '@/api/reports'

interface CashFlowChartProps {
    filters: ReportFilters
}

export function CashFlowChart({ filters }: CashFlowChartProps) {
    const [groupBy, setGroupBy] = useState<CashFlowGroupBy>('day')
    const { data, isLoading, error } = useCashFlowOverTime(filters, groupBy)

    const showComparison = filters.compareWith !== 'none'

    const { hasIncome, hasExpenses, noDataMessage } = useMemo(() => {
        if (!data?.items?.length) {
            return { hasIncome: false, hasExpenses: false, noDataMessage: 'No data for selected period' }
        }

        const totalIncome = data.items.reduce((sum, d) => sum + d.income, 0)
        const totalExpenses = data.items.reduce((sum, d) => sum + d.expenses, 0)

        const hasIncome = totalIncome > 0
        const hasExpenses = totalExpenses > 0

        let noDataMessage = null
        if (!hasIncome && !hasExpenses) {
            noDataMessage = 'No income or expenses for selected period'
        } else if (!hasIncome) {
            noDataMessage = 'No income data for selected period'
        } else if (!hasExpenses) {
            noDataMessage = 'No expenses data for selected period'
        }

        return { hasIncome, hasExpenses, noDataMessage }
    }, [data])

    const chartOption = useMemo(() => {
        if (!data?.items?.length || !hasIncome || !hasExpenses) return null

        const chartData = data.items
        const currency = data.currency

        const labels = chartData.map(d => d.label)
        const incomeData = chartData.map(d => d.income)
        const expensesData = chartData.map(d => -d.expenses) // Negative for downward bars
        const balanceData = chartData.map(d => d.balance)

        const series: any[] = [
            // Income bars (green, upward)
            {
                name: 'Income',
                type: 'bar',
                stack: 'current',
                data: incomeData,
                itemStyle: {
                    color: '#22c55e',
                    borderRadius: [4, 4, 0, 0],
                },
                barMaxWidth: 24,
            },
            // Expenses bars (red, downward)
            {
                name: 'Expenses',
                type: 'bar',
                stack: 'current',
                data: expensesData,
                itemStyle: {
                    color: '#ef4444',
                    borderRadius: [0, 0, 4, 4],
                },
                barMaxWidth: 24,
            },
            // Cumulative balance line
            {
                name: 'Balance',
                type: 'line',
                yAxisIndex: 1,
                data: balanceData,
                smooth: true,
                symbol: 'circle',
                symbolSize: 6,
                lineStyle: {
                    color: '#3b82f6',
                    width: 3,
                },
                itemStyle: {
                    color: '#3b82f6',
                    borderColor: '#fff',
                    borderWidth: 2,
                },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [
                            { offset: 0, color: 'rgba(59, 130, 246, 0.15)' },
                            { offset: 1, color: 'rgba(59, 130, 246, 0)' },
                        ],
                    },
                },
            },
        ]

        // Add comparison data if enabled
        if (showComparison) {
            const prevIncomeData = chartData.map(d => d.prevIncome || 0)
            const prevExpensesData = chartData.map(d => -(d.prevExpenses || 0))
            const prevBalanceData = chartData.map(d => d.prevBalance || 0)

            series.push(
                // Previous income (semi-transparent)
                {
                    name: 'Prev Income',
                    type: 'bar',
                    stack: 'previous',
                    data: prevIncomeData,
                    itemStyle: {
                        color: 'rgba(34, 197, 94, 0.3)',
                        borderRadius: [4, 4, 0, 0],
                    },
                    barMaxWidth: 24,
                    barGap: '-100%',
                },
                // Previous expenses (semi-transparent)
                {
                    name: 'Prev Expenses',
                    type: 'bar',
                    stack: 'previous',
                    data: prevExpensesData,
                    itemStyle: {
                        color: 'rgba(239, 68, 68, 0.3)',
                        borderRadius: [0, 0, 4, 4],
                    },
                    barMaxWidth: 24,
                },
                // Previous balance line (dashed)
                {
                    name: 'Prev Balance',
                    type: 'line',
                    yAxisIndex: 1,
                    data: prevBalanceData,
                    smooth: true,
                    symbol: 'none',
                    lineStyle: {
                        color: '#3b82f6',
                        width: 2,
                        type: 'dashed',
                        opacity: 0.5,
                    },
                }
            )
        }

        const formatValue = (val: number) => {
            const absVal = Math.abs(val)
            if (absVal >= 1000) return `${currency}${(val / 1000).toFixed(0)}k`
            return `${currency}${val}`
        }

        return {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'cross',
                    crossStyle: {
                        color: '#999',
                    },
                },
                formatter: (params: any[]) => {
                    const label = params[0]?.axisValue || ''
                    let html = `<div class="font-medium mb-2">${label}</div>`

                    // Current period
                    const income = params.find((p: any) => p.seriesName === 'Income')?.value || 0
                    const expenses = Math.abs(params.find((p: any) => p.seriesName === 'Expenses')?.value || 0)
                    const balance = params.find((p: any) => p.seriesName === 'Balance')?.value || 0

                    html += `<div class="space-y-1">`
                    html += `<div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        <span>Income: <strong>${currency}${income.toLocaleString()}</strong></span>
                    </div>`
                    html += `<div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        <span>Expenses: <strong>${currency}${expenses.toLocaleString()}</strong></span>
                    </div>`
                    html += `<div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        <span>Balance: <strong>${currency}${balance.toLocaleString()}</strong></span>
                    </div>`
                    html += `</div>`

                    if (showComparison) {
                        const prevIncome = params.find((p: any) => p.seriesName === 'Prev Income')?.value || 0
                        const prevExpenses = Math.abs(params.find((p: any) => p.seriesName === 'Prev Expenses')?.value || 0)
                        const prevBalance = params.find((p: any) => p.seriesName === 'Prev Balance')?.value || 0

                        html += `<div class="mt-2 pt-2 border-t border-gray-200 space-y-1 opacity-70">`
                        html += `<div class="text-xs text-gray-500 mb-1">Previous Period</div>`
                        html += `<div class="flex items-center gap-2 text-sm">
                            <span>Income: ${currency}${prevIncome.toLocaleString()}</span>
                        </div>`
                        html += `<div class="flex items-center gap-2 text-sm">
                            <span>Expenses: ${currency}${prevExpenses.toLocaleString()}</span>
                        </div>`
                        html += `<div class="flex items-center gap-2 text-sm">
                            <span>Balance: ${currency}${prevBalance.toLocaleString()}</span>
                        </div>`
                        html += `</div>`
                    }

                    return html
                },
            },
            legend: {
                data: ['Income', 'Expenses', 'Balance'],
                bottom: 0,
                textStyle: {
                    fontSize: 12,
                    color: '#64748b',
                },
            },
            grid: {
                left: 60,
                right: 60,
                top: 20,
                bottom: 50,
            },
            xAxis: {
                type: 'category',
                data: labels,
                axisLabel: {
                    fontSize: 11,
                    color: '#64748b',
                    rotate: groupBy === 'day' && chartData.length > 15 ? 45 : 0,
                    interval: groupBy === 'day' ? Math.floor(chartData.length / 10) : 0,
                },
                axisLine: {
                    lineStyle: { color: '#e2e8f0' },
                },
                axisTick: { show: false },
            },
            yAxis: [
                // Left Y-axis for bars (income/expenses)
                {
                    type: 'value',
                    name: 'Flow',
                    position: 'left',
                    axisLabel: {
                        formatter: formatValue,
                        fontSize: 11,
                        color: '#64748b',
                    },
                    splitLine: {
                        lineStyle: { color: '#f1f5f9', type: 'dashed' },
                    },
                },
                // Right Y-axis for balance line
                {
                    type: 'value',
                    name: 'Balance',
                    position: 'right',
                    axisLabel: {
                        formatter: formatValue,
                        fontSize: 11,
                        color: '#3b82f6',
                    },
                    splitLine: { show: false },
                },
            ],
            series,
        }
    }, [data, showComparison, groupBy, hasIncome, hasExpenses])

    if (error) {
        return (
            <Card>
                <CardContent className="py-8 text-center text-red-500">
                    Failed to load cash flow data
                </CardContent>
            </Card>
        )
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <div>
                        <CardTitle className="text-lg">Cash Flow Over Time</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Income, expenses, and running balance
                        </p>
                    </div>
                    <div className="flex items-center gap-4">
                        {/* Grouping toggle */}
                        <div className="flex gap-1">
                            {(['day', 'week', 'month'] as CashFlowGroupBy[]).map(g => (
                                <Badge
                                    key={g}
                                    variant={groupBy === g ? 'default' : 'outline'}
                                    className="cursor-pointer capitalize"
                                    onClick={() => setGroupBy(g)}
                                >
                                    {g}
                                </Badge>
                            ))}
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-[400px]" />
                ) : noDataMessage ? (
                    <div className="h-[400px] flex items-center justify-center text-muted-foreground">
                        {noDataMessage}
                    </div>
                ) : (
                    <ReactECharts
                        option={chartOption}
                        style={{ height: 400 }}
                    />
                )}
            </CardContent>
        </Card>
    )
}
