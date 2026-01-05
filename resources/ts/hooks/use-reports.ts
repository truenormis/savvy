import { useQuery } from '@tanstack/react-query'
import { reportsApi } from '@/api'
import type { ReportFilters } from '@/pages/reports/types'
import type { CashFlowGroupBy, TransactionType } from '@/api/reports'

export function useOverviewMetrics(filters: ReportFilters) {
    return useQuery({
        queryKey: ['reports', 'overview', filters],
        queryFn: () => reportsApi.getOverview(filters),
    })
}

export function useMoneyFlow(filters: ReportFilters) {
    return useQuery({
        queryKey: ['reports', 'money-flow', filters],
        queryFn: () => reportsApi.getMoneyFlow(filters),
    })
}

export function useExpensePace(filters: ReportFilters) {
    return useQuery({
        queryKey: ['reports', 'expense-pace', filters],
        queryFn: () => reportsApi.getExpensePace(filters),
    })
}

export function useExpensesByCategory(filters: ReportFilters) {
    return useQuery({
        queryKey: ['reports', 'expenses-by-category', filters],
        queryFn: () => reportsApi.getExpensesByCategory(filters),
    })
}

export function useCashFlowOverTime(filters: ReportFilters, groupBy: CashFlowGroupBy = 'day') {
    return useQuery({
        queryKey: ['reports', 'cash-flow-over-time', filters, groupBy],
        queryFn: () => reportsApi.getCashFlowOverTime(filters, groupBy),
    })
}

export function useActivityHeatmap(filters: ReportFilters) {
    return useQuery({
        queryKey: ['reports', 'activity-heatmap', filters],
        queryFn: () => reportsApi.getActivityHeatmap(filters),
    })
}

// Transaction Reports (Expenses/Income)
export function useTransactionReportSummary(filters: ReportFilters, type: TransactionType) {
    return useQuery({
        queryKey: ['reports', 'transaction-summary', filters, type],
        queryFn: () => reportsApi.getTransactionSummary(filters, type),
    })
}

export function useTransactionReportByCategory(filters: ReportFilters, type: TransactionType) {
    return useQuery({
        queryKey: ['reports', 'transactions-by-category', filters, type],
        queryFn: () => reportsApi.getTransactionsByCategory(filters, type),
    })
}

export function useTransactionReportDynamics(filters: ReportFilters, type: TransactionType, groupBy: CashFlowGroupBy = 'day') {
    return useQuery({
        queryKey: ['reports', 'transaction-dynamics', filters, type, groupBy],
        queryFn: () => reportsApi.getTransactionDynamics(filters, type, groupBy),
    })
}

export function useTransactionReportTop(filters: ReportFilters, type: TransactionType, limit: number = 10) {
    return useQuery({
        queryKey: ['reports', 'top-transactions', filters, type, limit],
        queryFn: () => reportsApi.getTopTransactions(filters, type, limit),
    })
}

// Net Worth
export function useNetWorth(filters: ReportFilters) {
    return useQuery({
        queryKey: ['reports', 'net-worth', filters],
        queryFn: () => reportsApi.getNetWorth(filters),
    })
}

export function useNetWorthHistory(filters: ReportFilters, groupBy: CashFlowGroupBy = 'day') {
    return useQuery({
        queryKey: ['reports', 'net-worth-history', filters, groupBy],
        queryFn: () => reportsApi.getNetWorthHistory(filters, groupBy),
    })
}
