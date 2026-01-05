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

// Helper to format date as YYYY-MM-DD (timezone-safe)
function formatDate(date: Date): string {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
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
    customStartDate: formatDate(new Date(now.getFullYear(), now.getMonth(), 1)),
    customEndDate: formatDate(now),
    compareWith: 'previous_period',
    accountIds: [],
    categoryIds: [],
    tagIds: [],
}

export const TABS: { value: ReportTab; label: string }[] = [
    { value: 'overview', label: 'Overview' },
    { value: 'cashflow', label: 'Cash Flow' },
    { value: 'expenses', label: 'Expenses' },
    { value: 'income', label: 'Income' },
    { value: 'networth', label: 'Net Worth' },
]
