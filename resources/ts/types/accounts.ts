import { BaseEntity } from './api'
import { Currency } from './currencies'

export type AccountType = 'bank' | 'crypto' | 'cash'

export interface Account extends BaseEntity {
    name: string
    type: AccountType
    currencyId: number
    initialBalance: number
    currentBalance: number
    isActive: boolean
    currency?: Currency
}

export interface AccountFormData {
    name: string
    type: AccountType
    currency_id: number
    initial_balance?: number
    is_active?: boolean
}
