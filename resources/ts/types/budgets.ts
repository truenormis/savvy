import { BaseEntity } from './api'
import { Category } from './categories'
import { Currency } from './currencies'
import { Tag } from './tags'

export type BudgetPeriod = 'weekly' | 'monthly' | 'yearly' | 'one_time'

export interface BudgetProgress {
    spent: number
    remaining: number
    percent: number
    period_start: string
    period_end: string
    is_exceeded: boolean
}

export interface Budget extends BaseEntity {
    name: string
    amount: number
    currencyId: number | null
    currency?: Currency
    period: BudgetPeriod
    periodLabel: string
    startDate: string | null
    endDate: string | null
    isGlobal: boolean
    notifyAtPercent: number | null
    isActive: boolean
    categories: Category[]
    tags: Tag[]
    progress?: BudgetProgress
}

export interface BudgetFormData {
    name: string
    amount: number
    currency_id?: number | null
    period: BudgetPeriod
    start_date?: string | null
    end_date?: string | null
    is_global: boolean
    notify_at_percent?: number | null
    is_active: boolean
    category_ids: number[]
    tag_ids?: number[]
}
