import { api, apiClient } from './client'
import { Transaction, TransactionFormData, TransactionFilters, TransactionSummary } from '@/types'

const ENDPOINT = '/transactions'

export interface TransactionsResponse {
    data: Transaction[]
    summary?: TransactionSummary
    meta?: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
}

export const transactionsApi = {
    getAll: async (filters?: TransactionFilters & { with_summary?: boolean }): Promise<TransactionsResponse> => {
        const searchParams = new URLSearchParams()
        if (filters?.type) searchParams.set('type', filters.type)
        if (filters?.account_id) searchParams.set('account_id', String(filters.account_id))
        if (filters?.category_id) searchParams.set('category_id', String(filters.category_id))
        if (filters?.category_ids?.length) {
            filters.category_ids.forEach(id => searchParams.append('category_ids[]', String(id)))
        }
        if (filters?.tag_ids?.length) {
            filters.tag_ids.forEach(id => searchParams.append('tag_ids[]', String(id)))
        }
        if (filters?.start_date) searchParams.set('start_date', filters.start_date)
        if (filters?.end_date) searchParams.set('end_date', filters.end_date)
        if (filters?.sort_by) searchParams.set('sort_by', filters.sort_by)
        if (filters?.sort_direction) searchParams.set('sort_direction', filters.sort_direction)
        if (filters?.per_page) searchParams.set('per_page', String(filters.per_page))
        if (filters?.page) searchParams.set('page', String(filters.page))
        if (filters?.with_summary) searchParams.set('with_summary', 'true')
        const query = searchParams.toString()
        // Don't unwrap - we need full response with data, summary and meta
        const response = await apiClient.get(`${ENDPOINT}${query ? `?${query}` : ''}`)
        return response.data
    },

    getById: (id: number | string) =>
        api.get<Transaction>(`${ENDPOINT}/${id}`),

    create: (data: TransactionFormData) =>
        api.post<Transaction, TransactionFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<TransactionFormData>) =>
        api.patch<Transaction, Partial<TransactionFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) =>
        api.delete<void>(`${ENDPOINT}/${id}`),

    duplicate: (id: number | string) =>
        api.post<Transaction, void>(`${ENDPOINT}/${id}/duplicate`, undefined),

    getSummary: (filters?: TransactionFilters) => {
        const searchParams = new URLSearchParams()
        if (filters?.type) searchParams.set('type', filters.type)
        if (filters?.account_id) searchParams.set('account_id', String(filters.account_id))
        if (filters?.category_id) searchParams.set('category_id', String(filters.category_id))
        if (filters?.start_date) searchParams.set('start_date', filters.start_date)
        if (filters?.end_date) searchParams.set('end_date', filters.end_date)
        const query = searchParams.toString()
        return api.get<TransactionSummary>(`${ENDPOINT}-summary${query ? `?${query}` : ''}`)
    },
}
