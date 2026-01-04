import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Wallet, ArrowDownLeft, ArrowUpRight, CreditCard, ArrowRight, Calendar, Plus, ArrowLeftRight, PiggyBank } from 'lucide-react'
import { Progress } from '@/components/ui/progress'
import { useTotalBalance, useTransactions, useBalanceHistory, useAccounts, useCategorySummary, useBudgets } from '@/hooks'
import { useMemo, useState } from 'react'
import ReactECharts from 'echarts-for-react'
import { useTheme } from '@/hooks/use-theme'
import { Link, useNavigate } from 'react-router-dom'
import { Transaction } from '@/types'

const CHART_COLORS = [
    '#3b82f6', // blue
    '#10b981', // green
    '#f59e0b', // amber
    '#ef4444', // red
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#84cc16', // lime
]

const PIE_COLORS = [
    '#ef4444', // red
    '#f97316', // orange
    '#f59e0b', // amber
    '#eab308', // yellow
    '#84cc16', // lime
    '#22c55e', // green
    '#14b8a6', // teal
    '#06b6d4', // cyan
    '#0ea5e9', // sky
    '#3b82f6', // blue
    '#6366f1', // indigo
    '#8b5cf6', // violet
    '#a855f7', // purple
    '#d946ef', // fuchsia
    '#ec4899', // pink
]

type PeriodPreset = 'this_month' | 'last_month' | 'last_3_months' | 'last_6_months' | 'this_year' | 'custom'

function formatDateLocal(date: Date): string {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

function getPresetDates(preset: PeriodPreset): { start_date: string; end_date: string } {
    const now = new Date()
    const year = now.getFullYear()
    const month = now.getMonth()

    switch (preset) {
        case 'this_month': {
            const firstDay = new Date(year, month, 1)
            const lastDay = new Date(year, month + 1, 0)
            return {
                start_date: formatDateLocal(firstDay),
                end_date: formatDateLocal(lastDay),
            }
        }
        case 'last_month': {
            const firstDay = new Date(year, month - 1, 1)
            const lastDay = new Date(year, month, 0)
            return {
                start_date: formatDateLocal(firstDay),
                end_date: formatDateLocal(lastDay),
            }
        }
        case 'last_3_months': {
            const firstDay = new Date(year, month - 2, 1)
            const lastDay = new Date(year, month + 1, 0)
            return {
                start_date: formatDateLocal(firstDay),
                end_date: formatDateLocal(lastDay),
            }
        }
        case 'last_6_months': {
            const firstDay = new Date(year, month - 5, 1)
            const lastDay = new Date(year, month + 1, 0)
            return {
                start_date: formatDateLocal(firstDay),
                end_date: formatDateLocal(lastDay),
            }
        }
        case 'this_year': {
            const firstDay = new Date(year, 0, 1)
            const lastDay = new Date(year, 11, 31)
            return {
                start_date: formatDateLocal(firstDay),
                end_date: formatDateLocal(lastDay),
            }
        }
        default:
            return getPresetDates('this_month')
    }
}

function formatDate(dateString: string): string {
    const date = new Date(dateString)
    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

function getTransactionSign(type: Transaction['type']): string {
    switch (type) {
        case 'income':
            return '+'
        case 'expense':
            return '-'
        default:
            return ''
    }
}

function getTransactionColor(type: Transaction['type']): string {
    switch (type) {
        case 'income':
            return 'text-green-600'
        case 'expense':
            return 'text-red-600'
        default:
            return 'text-muted-foreground'
    }
}

export default function DashboardPage() {
    const { theme } = useTheme()
    const navigate = useNavigate()
    const { data: balance } = useTotalBalance()
    const { data: accounts } = useAccounts({ active: true })

    // Period state for chart
    const [chartPeriod, setChartPeriod] = useState<PeriodPreset>('this_month')
    const [customStartDate, setCustomStartDate] = useState('')
    const [customEndDate, setCustomEndDate] = useState('')

    // Current month filters (for summary cards)
    const currentMonthFilters = useMemo(() => getPresetDates('this_month'), [])

    // Chart period filters
    const chartFilters = useMemo(() => {
        if (chartPeriod === 'custom' && customStartDate && customEndDate) {
            return { start_date: customStartDate, end_date: customEndDate }
        }
        return getPresetDates(chartPeriod)
    }, [chartPeriod, customStartDate, customEndDate])

    const { data: monthData } = useTransactions({ ...currentMonthFilters, with_summary: true })
    const { data: recentTransactions } = useTransactions({ per_page: 5 })
    const { data: historyData } = useBalanceHistory(chartFilters)
    const { data: expensesByCategory } = useCategorySummary({
        type: 'expense',
        ...currentMonthFilters,
    })
    const { data: budgets } = useBudgets()

    const activeBudgets = useMemo(() => {
        return budgets?.filter(b => b.isActive).slice(0, 4) ?? []
    }, [budgets])

    const summary = monthData?.summary

    const totalBalance = balance?.total_balance ?? 0
    const currency = balance?.currency ?? '$'
    const monthIncome = Number(summary?.income) || 0
    const monthExpense = Number(summary?.expense) || 0

    const balanceChartOption = useMemo(() => {
        if (!historyData || !historyData.series.length) return {}

        const isDark = theme === 'dark'

        const series = historyData.series.map((s, index) => {
            const isTotal = s.type === 'total'
            const color = isTotal ? '#6366f1' : CHART_COLORS[index % CHART_COLORS.length]

            return {
                name: s.name,
                type: 'line',
                smooth: true,
                data: s.data,
                lineStyle: {
                    color,
                    width: isTotal ? 3 : 2,
                },
                itemStyle: { color },
                areaStyle: isTotal
                    ? {
                          color: {
                              type: 'linear',
                              x: 0,
                              y: 0,
                              x2: 0,
                              y2: 1,
                              colorStops: [
                                  { offset: 0, color: 'rgba(99, 102, 241, 0.2)' },
                                  { offset: 1, color: 'rgba(99, 102, 241, 0.02)' },
                              ],
                          },
                      }
                    : undefined,
                emphasis: { focus: 'series' },
            }
        })

        // Determine label format based on date range
        const daysDiff = historyData.dates.length
        const formatLabel = (d: string) => {
            const date = new Date(d)
            if (daysDiff > 90) {
                return `${String(date.getMonth() + 1).padStart(2, '0')}.${date.getFullYear().toString().slice(2)}`
            }
            return `${date.getDate()}.${String(date.getMonth() + 1).padStart(2, '0')}`
        }

        return {
            tooltip: {
                trigger: 'axis',
                backgroundColor: isDark ? '#1f2937' : '#ffffff',
                borderColor: isDark ? '#374151' : '#e5e7eb',
                textStyle: { color: isDark ? '#f3f4f6' : '#1f2937' },
            },
            legend: {
                data: historyData.series.map((s) => s.name),
                bottom: 0,
                textStyle: { color: isDark ? '#9ca3af' : '#6b7280' },
                icon: 'roundRect',
                itemWidth: 14,
                itemHeight: 8,
            },
            grid: {
                left: '3%',
                right: '4%',
                bottom: '15%',
                top: '10%',
                containLabel: true,
            },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: historyData.dates.map(formatLabel),
                axisLine: { lineStyle: { color: isDark ? '#374151' : '#e5e7eb' } },
                axisLabel: {
                    color: isDark ? '#9ca3af' : '#6b7280',
                    interval: daysDiff > 60 ? Math.floor(daysDiff / 10) : 'auto',
                },
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false },
                splitLine: { lineStyle: { color: isDark ? '#374151' : '#e5e7eb' } },
                axisLabel: {
                    color: isDark ? '#9ca3af' : '#6b7280',
                    formatter: (value: number) =>
                        value >= 1000 ? `${(value / 1000).toFixed(0)}k` : value.toString(),
                },
            },
            series,
        }
    }, [historyData, theme])

    const pieChartOption = useMemo(() => {
        if (!expensesByCategory?.data.length) return {}

        const isDark = theme === 'dark'
        const categoryCurrency = expensesByCategory.currency || currency
        const data = expensesByCategory.data
            .filter((c) => (c.totalAmount ?? 0) > 0)
            .map((c, i) => ({
                name: c.name,
                value: c.totalAmount ?? 0,
                itemStyle: { color: c.color || PIE_COLORS[i % PIE_COLORS.length] },
            }))

        return {
            tooltip: {
                trigger: 'item',
                backgroundColor: isDark ? '#1f2937' : '#ffffff',
                borderColor: isDark ? '#374151' : '#e5e7eb',
                textStyle: { color: isDark ? '#f3f4f6' : '#1f2937' },
                formatter: (params: { name: string; value: number; percent: number }) =>
                    `${params.name}<br/>${params.value.toFixed(2)} ${categoryCurrency} (${params.percent.toFixed(1)}%)`,
            },
            legend: {
                orient: 'vertical',
                right: 10,
                top: 'center',
                textStyle: { color: isDark ? '#9ca3af' : '#6b7280' },
            },
            series: [
                {
                    type: 'pie',
                    radius: ['40%', '70%'],
                    center: ['35%', '50%'],
                    avoidLabelOverlap: false,
                    itemStyle: {
                        borderRadius: 4,
                        borderColor: isDark ? '#1f2937' : '#ffffff',
                        borderWidth: 2,
                    },
                    label: { show: false },
                    emphasis: {
                        label: { show: false },
                    },
                    data,
                },
            ],
        }
    }, [expensesByCategory, theme, currency])

    const handlePeriodChange = (value: PeriodPreset) => {
        setChartPeriod(value)
        if (value !== 'custom') {
            setCustomStartDate('')
            setCustomEndDate('')
        }
    }

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>
                <p className="text-muted-foreground">Welcome to your finance dashboard</p>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Total Balance
                        </CardTitle>
                        <Wallet className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold font-mono">
                            {totalBalance.toFixed(2)} {currency}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Income this month
                        </CardTitle>
                        <ArrowDownLeft className="size-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold font-mono text-green-600">
                            +{monthIncome.toFixed(2)} {currency}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Expenses this month
                        </CardTitle>
                        <ArrowUpRight className="size-4 text-red-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold font-mono text-red-600">
                            -{monthExpense.toFixed(2)} {currency}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Balance Dynamics</CardTitle>
                        <div className="flex items-center gap-2">
                            <Calendar className="size-4 text-muted-foreground" />
                            <Select value={chartPeriod} onValueChange={handlePeriodChange}>
                                <SelectTrigger className="w-[160px] h-8">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="this_month">This month</SelectItem>
                                    <SelectItem value="last_month">Last month</SelectItem>
                                    <SelectItem value="last_3_months">Last 3 months</SelectItem>
                                    <SelectItem value="last_6_months">Last 6 months</SelectItem>
                                    <SelectItem value="this_year">This year</SelectItem>
                                    <SelectItem value="custom">Custom</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    {chartPeriod === 'custom' && (
                        <div className="px-6 pb-2 flex items-center gap-2">
                            <Input
                                type="date"
                                value={customStartDate}
                                onChange={(e) => setCustomStartDate(e.target.value)}
                                className="h-8 w-[140px]"
                            />
                            <span className="text-muted-foreground">—</span>
                            <Input
                                type="date"
                                value={customEndDate}
                                onChange={(e) => setCustomEndDate(e.target.value)}
                                className="h-8 w-[140px]"
                            />
                        </div>
                    )}
                    <CardContent>
                        {historyData && historyData.series.length > 0 ? (
                            <ReactECharts
                                option={balanceChartOption}
                                style={{ height: '300px' }}
                                opts={{ renderer: 'svg' }}
                            />
                        ) : (
                            <div className="flex items-center justify-center h-[300px] text-muted-foreground">
                                No data for this period
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Account Balances</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {accounts && accounts.length > 0 ? (
                                accounts.map((account) => (
                                    <div
                                        key={account.id}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <div className="flex items-center gap-3 min-w-0 flex-1">
                                            <div className="flex size-9 items-center justify-center rounded-lg bg-muted shrink-0">
                                                <CreditCard className="size-4" />
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium truncate">{account.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {account.type}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <div className="text-right">
                                                <p className="text-sm font-mono font-medium">
                                                    {(account.currentBalance ?? 0).toFixed(2)}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {account.currency?.symbol ?? ''}
                                                </p>
                                            </div>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="size-8 shrink-0">
                                                        <Plus className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onClick={() => navigate(`/transactions/create?type=income&account_id=${account.id}`)}
                                                    >
                                                        <ArrowDownLeft className="size-4 mr-2 text-green-600" />
                                                        Income
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => navigate(`/transactions/create?type=expense&account_id=${account.id}`)}
                                                    >
                                                        <ArrowUpRight className="size-4 mr-2 text-red-600" />
                                                        Expense
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => navigate(`/transactions/create?type=transfer&account_id=${account.id}`)}
                                                    >
                                                        <ArrowLeftRight className="size-4 mr-2 text-blue-600" />
                                                        Transfer
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center text-muted-foreground py-4">
                                    No active accounts
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Expenses by Category</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {expensesByCategory && expensesByCategory.data.some((c) => (c.totalAmount ?? 0) > 0) ? (
                            <ReactECharts
                                option={pieChartOption}
                                style={{ height: '280px' }}
                                opts={{ renderer: 'svg' }}
                            />
                        ) : (
                            <div className="flex items-center justify-center h-[280px] text-muted-foreground">
                                No expenses this month
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Recent Transactions</CardTitle>
                        <Button variant="ghost" size="sm" asChild>
                            <Link to="/transactions">
                                View all
                                <ArrowRight className="ml-1 size-4" />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {recentTransactions?.data && recentTransactions.data.length > 0 ? (
                                recentTransactions.data.map((transaction) => (
                                    <div
                                        key={transaction.id}
                                        className="flex items-center justify-between"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div
                                                className="flex size-9 items-center justify-center rounded-lg"
                                                style={{
                                                    backgroundColor: transaction.category?.color
                                                        ? `${transaction.category.color}20`
                                                        : undefined,
                                                }}
                                            >
                                                {transaction.category?.icon ? (
                                                    <span className="text-sm">
                                                        {transaction.category.icon}
                                                    </span>
                                                ) : (
                                                    <CreditCard className="size-4" />
                                                )}
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {transaction.category?.name ||
                                                        transaction.description ||
                                                        (transaction.type === 'transfer'
                                                            ? 'Transfer'
                                                            : 'Transaction')}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDate(transaction.date)} &middot;{' '}
                                                    {transaction.account.name}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <p
                                                className={`text-sm font-mono font-medium ${getTransactionColor(transaction.type)}`}
                                            >
                                                {getTransactionSign(transaction.type)}
                                                {transaction.amount.toFixed(2)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {transaction.account.currency?.symbol ?? ''}
                                            </p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center text-muted-foreground py-4">
                                    No transactions yet
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <PiggyBank className="size-5" />
                        Budgets
                    </CardTitle>
                    <Button variant="ghost" size="sm" asChild>
                        <Link to="/budgets">
                            View all
                            <ArrowRight className="ml-1 size-4" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    {activeBudgets.length > 0 ? (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {activeBudgets.map((budget) => {
                                const progress = budget.progress
                                const percent = progress ? Math.min(progress.percent, 100) : 0
                                const isExceeded = progress?.is_exceeded ?? false
                                const symbol = budget.currency?.symbol ?? ''

                                return (
                                    <Link
                                        key={budget.id}
                                        to={`/budgets/${budget.id}/edit`}
                                        className="block p-4 rounded-lg border hover:bg-muted/50 transition-colors"
                                    >
                                        <div className="flex items-center justify-between mb-2">
                                            <p className="font-medium text-sm truncate">{budget.name}</p>
                                            <span className={`text-xs font-medium ${isExceeded ? 'text-red-600' : 'text-muted-foreground'}`}>
                                                {progress?.percent.toFixed(0)}%
                                            </span>
                                        </div>
                                        <Progress
                                            value={percent}
                                            className={`h-2 mb-2 ${isExceeded ? '[&>div]:bg-red-500' : ''}`}
                                        />
                                        <div className="flex justify-between text-xs text-muted-foreground">
                                            <span>{progress?.spent.toLocaleString() ?? 0} {symbol}</span>
                                            <span>{budget.amount.toLocaleString()} {symbol}</span>
                                        </div>
                                        <p className="text-xs text-muted-foreground mt-1">
                                            {budget.isGlobal
                                                ? 'All expenses'
                                                : budget.categories.map(c => c.name).join(', ') || 'No categories'}
                                        </p>
                                    </Link>
                                )
                            })}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <PiggyBank className="size-12 mx-auto text-muted-foreground/50 mb-3" />
                            <p className="text-muted-foreground mb-3">No budgets yet</p>
                            <Button asChild size="sm">
                                <Link to="/budgets/create">
                                    <Plus className="size-4 mr-1" />
                                    Create Budget
                                </Link>
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    )
}
