import { api } from './client'
import type { ReportFilters } from '@/pages/reports/types'

export interface MetricData {
    value: number
    previous: number | null
    sparkline: number[]
}

export interface OverviewMetrics {
    income: MetricData
    expenses: MetricData
    netCashFlow: MetricData
    savingsRate: MetricData
    currency: string
}

export interface SankeyNode {
    name: string
    itemStyle: { color: string }
}

export interface SankeyLink {
    source: string
    target: string
    value: number
}

export interface MoneyFlowData {
    nodes: SankeyNode[]
    links: SankeyLink[]
    totals: {
        income: number
        expenses: number
        savings: number
    }
    currency: string
}

export interface ExpensePaceMonth {
    label: string
    budget: number | null
    dailyExpenses: number[]
    currentDay: number | null
    daysInMonth: number
    totalSpent: number
    monthStart: string
    monthEnd: string
}

export interface ExpensePaceData {
    months: ExpensePaceMonth[]
    currency: string
}

export interface CategoryExpense {
    id: number
    name: string
    icon: string
    color: string
    current: number
    previous: number
}

export interface ExpensesByCategoryData {
    categories: CategoryExpense[]
    currency: string
}

export interface CashFlowDataPoint {
    label: string
    income: number
    expenses: number
    balance: number
    prevIncome?: number
    prevExpenses?: number
    prevBalance?: number
}

export interface CashFlowOverTimeData {
    items: CashFlowDataPoint[]
    currency: string
}

export type CashFlowGroupBy = 'day' | 'week' | 'month'

export interface HeatmapDataPoint {
    date: string
    value: number
    count: number
}

export interface ActivityHeatmapData {
    items: HeatmapDataPoint[]
    max: number
    currency: string
}

// Transaction Reports (Expenses/Income)
export type TransactionType = 'expense' | 'income'

export interface TransactionSummaryData {
    total: number
    previous: number | null
    avgPerDay: number
    avgPerWeek: number
    prevAvgPerDay: number | null
    prevAvgPerWeek: number | null
    daysInPeriod: number
    currency: string
}

export interface TransactionCategoryItem {
    id: number
    name: string
    icon: string
    color: string
    value: number
    percentage: number
}

export interface TransactionsByCategoryData {
    items: TransactionCategoryItem[]
    total: number
    currency: string
}

export interface TransactionDynamicsDataset {
    id: number
    name: string
    color: string
    data: number[]
}

export interface TransactionDynamicsData {
    labels: string[]
    datasets: TransactionDynamicsDataset[]
    currency: string
}

export interface TopTransactionItem {
    id: number
    description: string
    amount: number
    date: string
    category: {
        id: number
        name: string
        icon: string
        color: string
    }
    account: {
        id: number
        name: string
    }
}

export interface TopTransactionsData {
    items: TopTransactionItem[]
    currency: string
}

// Net Worth
export interface NetWorthAccount {
    id: number
    name: string
    type: string
    balance: number
    percentage: number
}

export interface NetWorthData {
    current: number
    previous: number | null
    change: number
    changePercent: number
    accounts: NetWorthAccount[]
    currency: string
}

export interface NetWorthHistoryData {
    labels: string[]
    values: number[]
    currency: string
}

function buildParams(filters: ReportFilters): Record<string, string | string[]> {
    const params: Record<string, string | string[]> = {
        period_type: filters.periodType,
        compare_with: filters.compareWith,
    }

    // Set period value based on period type
    switch (filters.periodType) {
        case 'month':
            params.period_value = filters.selectedMonth
            break
        case 'quarter':
            params.period_value = filters.selectedQuarter
            break
        case 'year':
            params.period_value = filters.selectedYear
            break
        case 'custom':
            params.start_date = filters.customStartDate
            params.end_date = filters.customEndDate
            break
    }

    // Array filters
    if (filters.accountIds.length > 0) {
        params['account_ids[]'] = filters.accountIds.map(String)
    }
    if (filters.categoryIds.length > 0) {
        params['category_ids[]'] = filters.categoryIds.map(String)
    }
    if (filters.tagIds.length > 0) {
        params['tag_ids[]'] = filters.tagIds.map(String)
    }

    return params
}

export const reportsApi = {
    getOverview: (filters: ReportFilters) =>
        api.get<OverviewMetrics>('/reports/overview', { params: buildParams(filters) }),

    getMoneyFlow: (filters: ReportFilters) =>
        api.get<MoneyFlowData>('/reports/money-flow', { params: buildParams(filters) }),

    getExpensePace: (filters: ReportFilters) =>
        api.get<ExpensePaceData>('/reports/expense-pace', { params: buildParams(filters) }),

    getExpensesByCategory: (filters: ReportFilters) =>
        api.get<ExpensesByCategoryData>('/reports/expenses-by-category', { params: buildParams(filters) }),

    getCashFlowOverTime: (filters: ReportFilters, groupBy: CashFlowGroupBy = 'day') =>
        api.get<CashFlowOverTimeData>('/reports/cash-flow-over-time', {
            params: { ...buildParams(filters), group_by: groupBy }
        }),

    getActivityHeatmap: (filters: ReportFilters) =>
        api.get<ActivityHeatmapData>('/reports/activity-heatmap', { params: buildParams(filters) }),

    // Transaction Reports (Expenses/Income)
    getTransactionSummary: (filters: ReportFilters, type: TransactionType) =>
        api.get<TransactionSummaryData>('/reports/transactions/summary', {
            params: { ...buildParams(filters), type }
        }),

    getTransactionsByCategory: (filters: ReportFilters, type: TransactionType) =>
        api.get<TransactionsByCategoryData>('/reports/transactions/by-category', {
            params: { ...buildParams(filters), type }
        }),

    getTransactionDynamics: (filters: ReportFilters, type: TransactionType, groupBy: CashFlowGroupBy = 'day') =>
        api.get<TransactionDynamicsData>('/reports/transactions/dynamics', {
            params: { ...buildParams(filters), type, group_by: groupBy }
        }),

    getTopTransactions: (filters: ReportFilters, type: TransactionType, limit: number = 10) =>
        api.get<TopTransactionsData>('/reports/transactions/top', {
            params: { ...buildParams(filters), type, limit }
        }),

    // Net Worth
    getNetWorth: (filters: ReportFilters) =>
        api.get<NetWorthData>('/reports/net-worth', { params: buildParams(filters) }),

    getNetWorthHistory: (filters: ReportFilters, groupBy: CashFlowGroupBy = 'day') =>
        api.get<NetWorthHistoryData>('/reports/net-worth-history', {
            params: { ...buildParams(filters), group_by: groupBy }
        }),
}
