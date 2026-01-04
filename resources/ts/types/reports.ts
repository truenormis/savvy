export type ReportType = 'expenses' | 'income' | 'expenses_and_income' | 'balance' | 'cash_flow' | 'budgets'

export type ReportGroupBy = 'categories' | 'days' | 'weeks' | 'months' | 'accounts' | 'tags' | 'none'

export type ReportMetric = 'sum' | 'count' | 'average' | 'min' | 'max' | 'median' | 'percent_of_total' | 'percent_of_income'

export type ReportCompareWith = 'previous_period' | 'same_period_last_year' | 'budget'

export interface ReportConfig {
    type: ReportType
    start_date: string
    end_date: string
    group_by?: ReportGroupBy
    sub_group_by?: ReportGroupBy
    metrics?: ReportMetric[]
    category_ids?: number[]
    account_ids?: number[]
    tag_ids?: number[]
    compare_with?: ReportCompareWith
}

export interface ReportDataItem {
    key: string | number
    label: string
    metrics: Record<string, number>
    children?: ReportDataItem[]
}

export interface ReportComparison {
    period: {
        start: string
        end: string
    }
    data: ReportDataItem[]
    totals: Record<string, number>
}

export interface ReportResult {
    type: ReportType
    period: {
        start: string
        end: string
    }
    group_by: ReportGroupBy
    metrics: ReportMetric[]
    data: ReportDataItem[]
    totals: Record<string, number>
    comparison?: ReportComparison
    budget_comparison?: {
        budget_id: number
        budget_name: string
        budget_amount: number
        spent: number
        remaining: number
        percent: number
        is_exceeded: boolean
    }[]
}

export interface ReportOption {
    value: string
    label: string
}

export interface ReportPreset {
    label: string
    start_date: string
    end_date: string
}

export interface ReportOptions {
    types: ReportOption[]
    group_by: ReportOption[]
    metrics: ReportOption[]
    compare_with: ReportOption[]
    presets: ReportPreset[]
}
