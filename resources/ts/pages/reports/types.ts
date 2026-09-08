import type { CashFlowGroupBy } from '@/api/reports'
import { parseDate, toDateString } from '@/lib/utils'

export type PeriodType = 'month' | 'quarter' | 'year' | 'ytd' | 'custom'
export type CompareType = 'none' | 'previous_period' | 'same_period_last_year'
export type ReportTab = 'overview' | 'cashflow' | 'expenses' | 'income' | 'networth'

export interface ReportFilters {
    periodType: PeriodType
    selectedMonth: string
    selectedQuarter: string
    selectedYear: string
    customStartDate: string
    customEndDate: string
    compareWith: CompareType
    accountIds: number[]
    categoryIds: number[]
    tagIds: number[]
}

// Helper to format date as YYYY-MM (timezone-safe)
function formatYearMonth(date: Date): string {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    return `${year}-${month}`
}

const now = new Date()

export const DEFAULT_FILTERS: ReportFilters = {
    periodType: 'month',
    selectedMonth: formatYearMonth(now),
    selectedQuarter: `${now.getFullYear()}-Q${Math.ceil((now.getMonth() + 1) / 3)}`,
    selectedYear: now.getFullYear().toString(),
    customStartDate: toDateString(new Date(now.getFullYear(), now.getMonth(), 1)),
    customEndDate: toDateString(now),
    compareWith: 'previous_period',
    accountIds: [],
    categoryIds: [],
    tagIds: [],
}

export function defaultGroupBy(filters: ReportFilters): CashFlowGroupBy {
    switch (filters.periodType) {
        case 'month':
            return 'day'
        case 'quarter':
            return 'week'
        case 'year':
            return 'month'
        case 'ytd':
            return 'week'
        case 'custom': {
            const start = parseDate(filters.customStartDate)
            const end = parseDate(filters.customEndDate)
            if (!start || !end) return 'day'
            const days = Math.round((end.getTime() - start.getTime()) / 86_400_000) + 1
            if (days <= 45) return 'day'
            if (days <= 185) return 'week'
            return 'month'
        }
        default:
            return 'day'
    }
}

export const TABS: { value: ReportTab; label: string }[] = [
    { value: 'overview', label: 'Overview' },
    { value: 'cashflow', label: 'Cash Flow' },
    { value: 'expenses', label: 'Expenses' },
    { value: 'income', label: 'Income' },
    { value: 'networth', label: 'Net Worth' },
]
