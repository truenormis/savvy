import { useMemo, useState } from 'react'
import ReactECharts from 'echarts-for-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { cn } from '@/lib/utils'
import { TrendingUp, TrendingDown, AlertCircle } from 'lucide-react'
import { useExpensePace } from '@/hooks'
import type { ReportFilters } from '../types'
import type { ExpensePaceMonth } from '@/api/reports'

interface ExpensePaceChartProps {
    filters: ReportFilters
}

interface MonthChartData {
    label: string
    budget: number | null
    hasBudget: boolean
    idealPace: number[]
    actualExpenses: number[]
    currentDay: number | null
    currentActual: number
    budgetRemaining: number
    forecastTotal: number
    forecastDiff: number
    isOverBudget: boolean
    dailyAverage: number
    days: number[]
    daysInMonth: number
    totalSpent: number
}

function processMonthData(month: ExpensePaceMonth): MonthChartData {
    const { budget, dailyExpenses, currentDay, daysInMonth, totalSpent, label } = month

    const hasBudget = budget !== null && budget > 0
    const isCurrentMonth = currentDay !== null && currentDay > 0
    const isPastMonth = currentDay === null && dailyExpenses.length > 0

    const currentActual = isCurrentMonth
        ? (dailyExpenses[currentDay - 1] ?? 0)
        : totalSpent

    let idealPace: number[] = []
    let budgetRemaining = 0

    if (hasBudget && budget) {
        const dailyBudget = budget / daysInMonth
        idealPace = Array.from({ length: daysInMonth }, (_, i) => Math.round(dailyBudget * (i + 1)))
        budgetRemaining = budget - currentActual
    }

    let forecastTotal = 0
    let dailyAverage = 0
    if (isCurrentMonth && currentActual > 0) {
        dailyAverage = currentActual / currentDay
        forecastTotal = Math.round(dailyAverage * daysInMonth)
    } else if (isPastMonth) {
        forecastTotal = totalSpent
        dailyAverage = totalSpent / daysInMonth
    }

    const forecastDiff = hasBudget && budget ? forecastTotal - budget : 0
    const isOverBudget = forecastDiff > 0

    const days = Array.from({ length: daysInMonth }, (_, i) => i + 1)
    const actualExpenses = isCurrentMonth ? dailyExpenses.slice(0, currentDay) : dailyExpenses

    return {
        label,
        budget: hasBudget ? budget : null,
        hasBudget,
        idealPace,
        actualExpenses,
        currentDay: isCurrentMonth ? currentDay : null,
        currentActual,
        budgetRemaining,
        forecastTotal,
        forecastDiff,
        isOverBudget,
        dailyAverage,
        days,
        daysInMonth,
        totalSpent,
    }
}

function buildChartOption(chartData: MonthChartData, currency: string) {
    const { days, idealPace, actualExpenses, currentDay, currentActual, budget, daysInMonth, hasBudget, forecastTotal } = chartData

    const maxValue = Math.max(
        hasBudget && budget ? budget * 1.1 : 0,
        forecastTotal * 1.1,
        currentActual * 1.5,
        100
    )

    const formatYAxis = (val: number) => {
        if (maxValue >= 10000) {
            return `${currency}${(val / 1000).toFixed(0)}k`
        } else if (maxValue >= 1000) {
            return `${currency}${(val / 1000).toFixed(1)}k`
        }
        return `${currency}${val}`
    }

    const series: any[] = []

    if (hasBudget && idealPace.length > 0) {
        series.push({
            name: 'Budget Pace',
            type: 'line',
            data: idealPace,
            smooth: true,
            symbol: 'none',
            showSymbol: false,
            lineStyle: { color: '#94a3b8', width: 2, type: 'dashed' },
            areaStyle: {
                color: {
                    type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
                    colorStops: [
                        { offset: 0, color: 'rgba(148, 163, 184, 0.1)' },
                        { offset: 1, color: 'rgba(148, 163, 184, 0)' },
                    ],
                },
            },
        })
    }

    series.push({
        name: 'Actual',
        type: 'line',
        data: actualExpenses,
        smooth: true,
        symbol: 'none',
        showSymbol: false,
        lineStyle: { color: '#ef4444', width: 3 },
        areaStyle: {
            color: {
                type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
                colorStops: [
                    { offset: 0, color: 'rgba(239, 68, 68, 0.2)' },
                    { offset: 1, color: 'rgba(239, 68, 68, 0)' },
                ],
            },
        },
    })

    if (currentDay !== null && currentDay > 0) {
        series.push({
            name: 'Current',
            type: 'scatter',
            data: [[currentDay, currentActual]],
            symbol: 'circle',
            symbolSize: 12,
            itemStyle: {
                color: '#ef4444',
                borderColor: '#fff',
                borderWidth: 2,
                shadowColor: 'rgba(239, 68, 68, 0.4)',
                shadowBlur: 8,
            },
            label: {
                show: true,
                position: 'top',
                formatter: 'Today',
                fontSize: 11,
                color: '#ef4444',
                fontWeight: 'bold',
                distance: 8,
            },
            z: 10,
        })
    }

    if (hasBudget && budget) {
        series.push({
            name: 'Budget',
            type: 'line',
            data: Array.from({ length: daysInMonth }, () => budget),
            symbol: 'none',
            lineStyle: { color: '#22c55e', width: 2, type: 'dotted' },
            markLine: {
                silent: true,
                symbol: 'none',
                label: {
                    show: true,
                    position: 'end',
                    formatter: `Budget: ${currency}${budget.toLocaleString()}`,
                    fontSize: 11,
                    color: '#22c55e',
                },
                data: [{ yAxis: budget }],
                lineStyle: { color: '#22c55e', type: 'dotted' },
            },
        })
    }

    return {
        tooltip: {
            trigger: 'axis',
            formatter: (params: { seriesName: string; value: number; axisValue: number }[]) => {
                const day = params[0]?.axisValue
                let html = `<div class="font-medium mb-1">Day ${day}</div>`
                params.forEach(p => {
                    if (p.value !== undefined && p.seriesName !== 'Current') {
                        const colors: Record<string, string> = {
                            'Actual': '#ef4444',
                            'Budget Pace': '#94a3b8',
                            'Budget': '#22c55e',
                        }
                        const color = colors[p.seriesName] || '#64748b'
                        html += `<div class="flex items-center gap-2">
                            <span style="background:${color}" class="w-2 h-2 rounded-full inline-block"></span>
                            <span>${p.seriesName}: <strong>${currency}${p.value.toLocaleString()}</strong></span>
                        </div>`
                    }
                })
                return html
            },
        },
        grid: { left: 60, right: 20, top: 40, bottom: 40 },
        xAxis: {
            type: 'category',
            data: days,
            axisLabel: { interval: Math.floor(daysInMonth / 7), fontSize: 11, color: '#64748b' },
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisTick: { show: false },
        },
        yAxis: {
            type: 'value',
            axisLabel: { formatter: formatYAxis, fontSize: 11, color: '#64748b' },
            splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } },
            max: maxValue,
        },
        series,
    }
}

export function ExpensePaceChart({ filters }: ExpensePaceChartProps) {
    const { data, isLoading, error } = useExpensePace(filters)
    const [selectedMonth, setSelectedMonth] = useState(0)

    const monthsData = useMemo(() => {
        if (!data?.months?.length) return null
        return data.months.map(processMonthData)
    }, [data])

    const currentMonthData = monthsData?.[selectedMonth]
    const chartOption = useMemo(() => {
        if (!currentMonthData || !data) return null
        return buildChartOption(currentMonthData, data.currency)
    }, [currentMonthData, data])

    const formatCurrency = (val: number) => {
        return `${data?.currency ?? '$'}${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
    }

    if (error) {
        return (
            <Card>
                <CardContent className="py-8 text-center text-red-500">
                    Failed to load expense pace data
                </CardContent>
            </Card>
        )
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <div>
                        <CardTitle className="text-lg">Expense Pace</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {currentMonthData?.hasBudget
                                ? 'Track your spending against the monthly budget'
                                : 'Track your daily spending progress'
                            }
                        </p>
                    </div>
                    {currentMonthData && (
                        <div className="text-right">
                            <p className="text-sm text-muted-foreground">
                                {currentMonthData.hasBudget ? 'Budget Remaining' : 'Spent So Far'}
                            </p>
                            <p className={cn(
                                'text-2xl font-bold',
                                currentMonthData.hasBudget
                                    ? (currentMonthData.budgetRemaining >= 0 ? 'text-green-600' : 'text-red-600')
                                    : 'text-slate-700'
                            )}>
                                {currentMonthData.hasBudget
                                    ? formatCurrency(currentMonthData.budgetRemaining)
                                    : formatCurrency(currentMonthData.currentActual)
                                }
                            </p>
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-[300px]" />
                ) : !chartOption || !monthsData ? (
                    <div className="h-[300px] flex items-center justify-center text-muted-foreground">
                        No expense data for selected period
                    </div>
                ) : (
                    <>
                        {monthsData.length > 1 && (
                            <div className="mb-4">
                                <Select value={String(selectedMonth)} onValueChange={(v) => setSelectedMonth(Number(v))}>
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {monthsData.map((m, i) => (
                                            <SelectItem key={i} value={String(i)}>{m.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <ReactECharts option={chartOption} style={{ height: 300 }} />

                        {currentMonthData && (
                            <div className="mt-4 pt-4 border-t">
                                {!currentMonthData.hasBudget && (
                                    <div className="flex items-center gap-2 text-sm text-amber-600 mb-3">
                                        <AlertCircle className="size-4" />
                                        <span>No budget set. Create a budget to track your spending goals.</span>
                                    </div>
                                )}

                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            {currentMonthData.currentDay ? 'Projected by end of month' : 'Total spent'}
                                        </p>
                                        <p className="text-lg font-semibold">
                                            {currentMonthData.currentDay ? formatCurrency(currentMonthData.forecastTotal) : formatCurrency(currentMonthData.totalSpent)}
                                        </p>
                                    </div>
                                    {currentMonthData.hasBudget && (
                                        <div className={cn(
                                            'flex items-center gap-2 px-3 py-2 rounded-lg',
                                            currentMonthData.isOverBudget ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'
                                        )}>
                                            {currentMonthData.isOverBudget ? <TrendingUp className="size-4" /> : <TrendingDown className="size-4" />}
                                            <span className="text-sm font-medium">
                                                {currentMonthData.isOverBudget ? '+' : ''}{formatCurrency(Math.abs(currentMonthData.forecastDiff))} {currentMonthData.isOverBudget ? 'over' : 'under'} budget
                                            </span>
                                        </div>
                                    )}
                                </div>
                                {currentMonthData.currentDay ? (
                                    <p className="text-xs text-muted-foreground mt-2">
                                        {formatCurrency(currentMonthData.currentActual)} spent in {currentMonthData.currentDay} days = {formatCurrency(currentMonthData.dailyAverage)}/day avg × {currentMonthData.daysInMonth} days
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted-foreground mt-2">
                                        {formatCurrency(currentMonthData.totalSpent)} spent over {currentMonthData.daysInMonth} days = {formatCurrency(currentMonthData.dailyAverage)}/day avg
                                    </p>
                                )}
                            </div>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    )
}