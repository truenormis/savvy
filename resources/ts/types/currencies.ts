import { BaseEntity } from './api'

export interface Currency extends BaseEntity {
    code: string
    name: string
    symbol: string
    decimals: number
    isBase: boolean
    rate: number
}

export interface CurrencyCatalogItem {
    code: string
    name: string
    symbol: string
    decimals: number
    rate: number | null
}

export interface CurrencyFormData {
    code: string
    name: string
    symbol: string
    decimals: number
    isBase?: boolean
    rate?: number
}
