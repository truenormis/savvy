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
import {
    Wallet,
    ArrowDownLeft,
    ArrowUpRight,
    ArrowRight,
    Calendar,
    Plus,
    ArrowLeftRight,
    PiggyBank,
    CreditCard,
    HandCoins,
    Banknote,
    TrendingDown,
    TrendingUp,
    Repeat
} from 'lucide-react'
import { Progress } from '@/components/ui/progress'
import { useTotalBalance, useTransactions, useBalanceHistory, useAccounts, useCategorySummary, useBudgets, useDebtsWithSummary, useBalanceComparison, useUpcomingRecurring } from '@/hooks'
import { useOverviewMetrics } from '@/hooks/use-reports'
import type { ReportFilters } from '@/pages/reports/types'
import { cn, formatAmount } from '@/lib/utils'
import { useMemo, useState } from 'react'
import ReactECharts from 'echarts-for-react'
import { useTheme } from '@/hooks/use-theme'
import { Link, useNavigate } from 'react-router-dom'
import { Transaction, AccountType } from '@/types'
import { ACCOUNT_TYPE_CONFIG, CHART_COLORS, CATEGORY_COLORS } from '@/constants'

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
    const { data: accounts } = useAccounts({ active: true, exclude_debts: true })

    // Period state for chart
    const [chartPeriod, setChartPeriod] = useState<PeriodPreset>('this_month')
    const [customStartDate, setCustomStartDate] = useState('')
    const [customEndDate, setCustomEndDate] = useState('')

    // Current month filters (for summary cards)
    const currentMonthFilters = useMemo(() => getPresetDates('this_month'), [])

    // Report filters for income/expense comparison
    const reportFilters: ReportFilters = useMemo(() => {
        const now = new Date()
        const year = now.getFullYear()
        const month = String(now.getMonth() + 1).padStart(2, '0')
        return {
            periodType: 'month',
            selectedMonth: `${year}-${month}`,
            selectedQuarter: `${year}-Q${Math.ceil((now.getMonth() + 1) / 3)}`,
            selectedYear: year.toString(),
            customStartDate: '',
            customEndDate: '',
            compareWith: 'previous_period',
            accountIds: [],
            categoryIds: [],
            tagIds: [],
        }
    }, [])

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
    const { data: debtsData } = useDebtsWithSummary()
    const { data: overviewData } = useOverviewMetrics(reportFilters)
    const { data: balanceComparison } = useBalanceComparison()
    const { data: upcomingRecurring } = useUpcomingRecurring()

    const activeBudgets = useMemo(() => {
        return budgets?.filter(b => b.isActive).slice(0, 4) ?? []
    }, [budgets])

    const activeDebts = debtsData?.data?.filter(d => !d.isPaidOff).slice(0, 4) ?? []
    const debtSummary = debtsData?.summary

    const summary = monthData?.summary

    const totalBalance = balance?.total_balance ?? 0
    const currency = balance?.currency ?? '$'
    const decimals = balance?.decimals ?? 2
    const monthIncome = Number(summary?.income) || 0
    const monthExpense = Number(summary?.expense) || 0

    // Calculate percentage changes
    const balanceChange = useMemo(() => {
        if (balanceComparison?.previous == null) return null
        const current = balanceComparison.current
        const previous = balanceComparison.previous
        if (previous === 0 && current === 0) return 0
        if (previous === 0) return 100
        return ((current - previous) / Math.abs(previous)) * 100
    }, [balanceComparison])

    const incomeChange = useMemo(() => {
        if (overviewData?.income?.previous == null) return null
        const current = overviewData.income.value
        const previous = overviewData.income.previous
        if (previous === 0 && current === 0) return 0
        if (previous === 0) return 100
        return ((current - previous) / previous) * 100
    }, [overviewData])

    const expenseChange = useMemo(() => {
        if (overviewData?.expenses?.previous == null) return null
        const current = overviewData.expenses.value
        const previous = overviewData.expenses.previous
        if (previous === 0 && current === 0) return 0
        if (previous === 0) return 100
        return ((current - previous) / previous) * 100
    }, [overviewData])

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
                itemStyle: { color: c.color || CATEGORY_COLORS[i % CATEGORY_COLORS.length] },
            }))

        return {
            tooltip: {
                trigger: 'item',
                backgroundColor: isDark ? '#1f2937' : '#ffffff',
                borderColor: isDark ? '#374151' : '#e5e7eb',
                textStyle: { color: isDark ? '#f3f4f6' : '#1f2937' },
                formatter: (params: { name: string; value: number; percent: number }) =>
                    `${params.name}<br/>${params.value.toFixed(decimals)} ${categoryCurrency} (${params.percent.toFixed(1)}%)`,
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
    }, [expensesByCategory, theme, currency, decimals])

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
                            {totalBalance.toFixed(decimals)} {currency}
                        </div>
                        {balanceChange !== null && (
                            <div className={cn(
                                "flex items-center gap-1 text-xs mt-1",
                                balanceChange >= 0 ? "text-green-600" : "text-red-600"
                            )}>
                                {balanceChange >= 0 ? (
                                    <TrendingUp className="size-3" />
                                ) : (
                                    <TrendingDown className="size-3" />
                                )}
                                <span>{Math.abs(balanceChange).toFixed(1)}%</span>
                            </div>
                        )}
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
                            +{monthIncome.toFixed(decimals)} {currency}
                        </div>
                        {incomeChange !== null && (
                            <div className={cn(
                                "flex items-center gap-1 text-xs mt-1",
                                incomeChange >= 0 ? "text-green-600" : "text-red-600"
                            )}>
                                {incomeChange >= 0 ? (
                                    <TrendingUp className="size-3" />
                                ) : (
                                    <TrendingDown className="size-3" />
                                )}
                                <span>{Math.abs(incomeChange).toFixed(1)}%</span>
                            </div>
                        )}
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
                            -{monthExpense.toFixed(decimals)} {currency}
                        </div>
                        {expenseChange !== null && (
                            <div className={cn(
                                "flex items-center gap-1 text-xs mt-1",
                                expenseChange <= 0 ? "text-green-600" : "text-red-600"
                            )}>
                                {expenseChange >= 0 ? (
                                    <TrendingUp className="size-3" />
                                ) : (
                                    <TrendingDown className="size-3" />
                                )}
                                <span>{Math.abs(expenseChange).toFixed(1)}%</span>
                            </div>
                        )}
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
                                            {(() => {
                                                const config = ACCOUNT_TYPE_CONFIG[account.type as AccountType]
                                                const Icon = config?.icon || Wallet
                                                return (
                                                    <div className={`flex size-9 items-center justify-center rounded-lg shrink-0 ${config?.color || 'bg-muted'}`}>
                                                        <Icon className="size-4" />
                                                    </div>
                                                )
                                            })()}
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium truncate">{account.name}</p>
                                                <p className="text-xs text-muted-foreground capitalize">
                                                    {ACCOUNT_TYPE_CONFIG[account.type as AccountType]?.label || account.type}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <div className="text-right">
                                                <p className="text-sm font-mono font-medium">
                                                    {(account.currentBalance ?? 0).toFixed(account.currency?.decimals ?? 2)}
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
                                                {transaction.amount.toFixed(transaction.account.currency?.decimals ?? 2)}
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

            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <HandCoins className="size-5" />
                        Debts
                    </CardTitle>
                    <Button variant="ghost" size="sm" asChild>
                        <Link to="/debts">
                            View all
                            <ArrowRight className="ml-1 size-4" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    {debtSummary && (debtSummary.total_i_owe > 0 || debtSummary.total_owed_to_me > 0) ? (
                        <div className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="flex items-center gap-3 p-3 rounded-lg bg-red-50 dark:bg-red-950/20">
                                    <div className="p-2 rounded-lg bg-red-100 dark:bg-red-900/30">
                                        <TrendingDown className="size-4 text-red-600" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">I Owe</p>
                                        <p className="font-mono font-semibold text-red-600">
                                            {debtSummary.total_i_owe.toFixed(decimals)} {debtSummary.currency}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 p-3 rounded-lg bg-green-50 dark:bg-green-950/20">
                                    <div className="p-2 rounded-lg bg-green-100 dark:bg-green-900/30">
                                        <TrendingUp className="size-4 text-green-600" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Owed to Me</p>
                                        <p className="font-mono font-semibold text-green-600">
                                            {debtSummary.total_owed_to_me.toFixed(decimals)} {debtSummary.currency}
                                        </p>
                                    </div>
                                </div>
                                <div className={`flex items-center gap-3 p-3 rounded-lg ${debtSummary.net_debt >= 0 ? 'bg-green-50 dark:bg-green-950/20' : 'bg-red-50 dark:bg-red-950/20'}`}>
                                    <div className={`p-2 rounded-lg ${debtSummary.net_debt >= 0 ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'}`}>
                                        {debtSummary.net_debt >= 0 ? (
                                            <HandCoins className="size-4 text-green-600" />
                                        ) : (
                                            <Banknote className="size-4 text-red-600" />
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Net Position</p>
                                        <p className={`font-mono font-semibold ${debtSummary.net_debt >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                            {Math.abs(debtSummary.net_debt).toFixed(decimals)} {debtSummary.currency}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {activeDebts.length > 0 && (
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    {activeDebts.map((debt) => (
                                        <Link
                                            key={debt.id}
                                            to={`/debts/${debt.id}/edit`}
                                            className="block p-3 rounded-lg border hover:bg-muted/50 transition-colors"
                                        >
                                            <div className="flex items-center gap-2 mb-2">
                                                {debt.debtType === 'i_owe' ? (
                                                    <Banknote className="size-4 text-red-600" />
                                                ) : (
                                                    <HandCoins className="size-4 text-green-600" />
                                                )}
                                                <p className="font-medium text-sm truncate">{debt.name}</p>
                                            </div>
                                            <Progress
                                                value={debt.paymentProgress}
                                                className="h-1.5 mb-2"
                                            />
                                            <div className="flex justify-between text-xs">
                                                <span className="text-muted-foreground">
                                                    {debt.paymentProgress.toFixed(0)}% paid
                                                </span>
                                                <span className={debt.debtType === 'i_owe' ? 'text-red-600' : 'text-green-600'}>
                                                    {debt.remainingDebt.toFixed(debt.currency?.decimals ?? 2)} {debt.currency?.symbol}
                                                </span>
                                            </div>
                                            {debt.counterparty && (
                                                <p className="text-xs text-muted-foreground mt-1 truncate">
                                                    {debt.debtType === 'i_owe' ? 'To: ' : 'From: '}{debt.counterparty}
                                                </p>
                                            )}
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <HandCoins className="size-12 mx-auto text-muted-foreground/50 mb-3" />
                            <p className="text-muted-foreground mb-3">No active debts</p>
                            <Button asChild size="sm">
                                <Link to="/debts/create">
                                    <Plus className="size-4 mr-1" />
                                    Add Debt
                                </Link>
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Repeat className="size-5" />
                        Upcoming Recurring
                    </CardTitle>
                    <Button variant="ghost" size="sm" asChild>
                        <Link to="/recurring">
                            View all
                            <ArrowRight className="ml-1 size-4" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    {upcomingRecurring && upcomingRecurring.length > 0 ? (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            {upcomingRecurring.slice(0, 5).map((recurring) => (
                                <Link
                                    key={recurring.id}
                                    to={`/recurring/${recurring.id}/edit`}
                                    className="block p-3 rounded-lg border hover:bg-muted/50 transition-colors"
                                >
                                    <div className="flex items-center gap-2 mb-2">
                                        {recurring.type === 'income' ? (
                                            <ArrowDownLeft className="size-4 text-green-600" />
                                        ) : recurring.type === 'expense' ? (
                                            <ArrowUpRight className="size-4 text-red-600" />
                                        ) : (
                                            <ArrowLeftRight className="size-4 text-blue-600" />
                                        )}
                                        <p className="font-medium text-sm truncate">
                                            {recurring.description || recurring.category?.name || 'Recurring'}
                                        </p>
                                    </div>
                                    <p className={`font-mono text-sm ${
                                        recurring.type === 'income' ? 'text-green-600' :
                                        recurring.type === 'expense' ? 'text-red-600' : ''
                                    }`}>
                                        {recurring.type === 'income' ? '+' : recurring.type === 'expense' ? '-' : ''}
                                        {recurring.amount.toFixed(recurring.account.currency?.decimals ?? 2)} {recurring.account.currency?.symbol}
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {new Date(recurring.nextRunDate).toLocaleDateString('en-US', {
                                            month: 'short',
                                            day: 'numeric'
                                        })}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <Repeat className="size-12 mx-auto text-muted-foreground/50 mb-3" />
                            <p className="text-muted-foreground mb-3">No recurring transactions</p>
                            <Button asChild size="sm">
                                <Link to="/recurring/create">
                                    <Plus className="size-4 mr-1" />
                                    Create Recurring
                                </Link>
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    )
}
