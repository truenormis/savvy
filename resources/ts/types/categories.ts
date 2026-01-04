import { BaseEntity } from './api'

export type CategoryType = 'income' | 'expense'

export interface Category extends BaseEntity {
    name: string
    type: CategoryType
    icon: string
    color: string
    transactionsCount?: number
    totalAmount?: number
}

export interface CategoryFormData {
    name: string
    type: CategoryType
    icon: string
    color: string
}

export interface CategorySummaryResponse {
    data: Category[]
    total: number
    currency: string
}
