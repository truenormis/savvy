import { BaseEntity } from './api'
import { Account } from './accounts'
import { Category } from './categories'
import { Tag } from './tags'

export type TransactionType = 'income' | 'expense' | 'transfer' | 'debt_payment' | 'debt_collection'

export interface TransactionItem {
    id?: number
    name: string
    quantity: number
    pricePerUnit: number
    totalPrice: number
}

export interface Transaction extends BaseEntity {
    type: TransactionType
    amount: number
    toAmount?: number
    exchangeRate?: number
    description?: string
    date: string
    account: Account
    toAccount?: Account
    category?: Category
    items: TransactionItem[]
    itemsCount?: number
    tags: Tag[]
}

export interface TransactionItemFormData {
    name: string
    quantity: number
    price_per_unit: number
}

export interface TransactionFormData {
    type: TransactionType
    account_id: number
    to_account_id?: number
    category_id?: number
    amount: number
    to_amount?: number
    exchange_rate?: number
    description?: string
    date: string
    items?: TransactionItemFormData[]
    tag_ids?: number[]
}

export interface TransactionFilters {
    type?: TransactionType
    account_id?: number
    category_id?: number
    category_ids?: number[]
    tag_ids?: number[]
    start_date?: string
    end_date?: string
    sort_by?: 'date' | 'amount' | 'created_at'
    sort_direction?: 'asc' | 'desc'
    per_page?: number
    page?: number
}

export interface TransactionSummary {
    income: number
    expense: number
    balance: number
    transactions_count: number
    currency: string
}
