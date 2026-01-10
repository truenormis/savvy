import { api, apiClient } from './client'
import { Account, AccountFormData } from '@/types'

const ENDPOINT = '/accounts'

export interface AccountsSummary {
    total_balance: number
    currency: string
    currency_code: string
    decimals: number
    accounts_count: number
}

export interface AccountsResponse {
    data: Account[]
    summary?: AccountsSummary
}

export interface BalanceHistorySeries {
    name: string
    type: string
    data: number[]
}

export interface BalanceHistoryResponse {
    dates: string[]
    series: BalanceHistorySeries[]
    currency: string
    decimals: number
}

export interface BalanceComparisonResponse {
    current: number
    previous: number | null
    currency: string
    decimals: number
}

export const accountsApi = {
    getAll: (params?: { active?: boolean; exclude_debts?: boolean }) => {
        const searchParams = new URLSearchParams()
        if (params?.active) searchParams.set('active', 'true')
        if (params?.exclude_debts) searchParams.set('exclude_debts', 'true')
        const query = searchParams.toString()
        return api.get<Account[]>(`${ENDPOINT}${query ? `?${query}` : ''}`)
    },

    getAllWithSummary: async (params?: { active?: boolean; exclude_debts?: boolean }): Promise<AccountsResponse> => {
        const searchParams = new URLSearchParams()
        searchParams.set('with_summary', 'true')
        if (params?.active) searchParams.set('active', 'true')
        if (params?.exclude_debts) searchParams.set('exclude_debts', 'true')
        const query = searchParams.toString()
        const response = await apiClient.get(`${ENDPOINT}?${query}`)
        return response.data
    },

    getById: (id: number | string) =>
        api.get<Account>(`${ENDPOINT}/${id}`),

    create: (data: AccountFormData) =>
        api.post<Account, AccountFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<AccountFormData>) =>
        api.patch<Account, Partial<AccountFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) =>
        api.delete<void>(`${ENDPOINT}/${id}`),

    getBalanceHistory: async (params?: { start_date?: string; end_date?: string }): Promise<BalanceHistoryResponse> => {
        const searchParams = new URLSearchParams()
        if (params?.start_date) searchParams.set('start_date', params.start_date)
        if (params?.end_date) searchParams.set('end_date', params.end_date)
        const query = searchParams.toString()
        const response = await apiClient.get(`${ENDPOINT}-balance-history${query ? `?${query}` : ''}`)
        return response.data
    },

    getBalanceComparison: async (): Promise<BalanceComparisonResponse> => {
        const response = await apiClient.get(`${ENDPOINT}-balance-comparison`)
        return response.data
    },
}
