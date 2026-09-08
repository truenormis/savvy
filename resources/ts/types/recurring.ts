import { BaseEntity } from './api'
import { Account } from './accounts'
import { Category } from './categories'
import { Tag } from './tags'

export type RecurringFrequency = 'daily' | 'weekly' | 'monthly' | 'yearly'
export type RecurringTransactionType = 'income' | 'expense' | 'transfer'

export interface RecurringTransaction extends BaseEntity {
    type: RecurringTransactionType
    accountId: number
    toAccountId?: number
    categoryId?: number
    amount: number
    toAmount?: number
    description?: string | null
    frequency: RecurringFrequency
    frequencyLabel: string
    interval: number
    dayOfWeek?: number
    dayOfMonth?: number
    startDate: string
    endDate?: string
    nextRunDate: string
    lastRunDate?: string
    isActive: boolean
    account: Account
    toAccount?: Account
    category?: Category
    tags: Tag[]
}

export interface RecurringFormData {
    type: RecurringTransactionType
    account_id: number
    to_account_id?: number | null
    category_id?: number | null
    amount: number
    to_amount?: number | null
    description?: string | null
    frequency: RecurringFrequency
    interval: number
    day_of_week?: number | null
    day_of_month?: number | null
    start_date: string
    end_date?: string | null
    is_active: boolean
    tag_ids?: number[]
}
