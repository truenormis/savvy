import { api, apiClient } from './client'
import { Debt, DebtFormData, DebtPaymentFormData, DebtSummary, DebtsResponse } from '@/types'
import { Transaction } from '@/types'

const ENDPOINT = '/debts'

export const debtsApi = {
    getAll: (params?: { include_completed?: boolean }) => {
        const searchParams = new URLSearchParams()
        if (params?.include_completed) searchParams.set('include_completed', 'true')
        const query = searchParams.toString()
        return api.get<Debt[]>(`${ENDPOINT}${query ? `?${query}` : ''}`)
    },

    getAllWithSummary: async (params?: { include_completed?: boolean }): Promise<DebtsResponse> => {
        const searchParams = new URLSearchParams()
        searchParams.set('with_summary', 'true')
        if (params?.include_completed) searchParams.set('include_completed', 'true')
        const query = searchParams.toString()
        const response = await apiClient.get(`${ENDPOINT}?${query}`)
        return response.data
    },

    getById: (id: number | string) =>
        api.get<Debt>(`${ENDPOINT}/${id}`),

    create: (data: DebtFormData) =>
        api.post<Debt, DebtFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<DebtFormData>) =>
        api.patch<Debt, Partial<DebtFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) =>
        api.delete<void>(`${ENDPOINT}/${id}`),

    makePayment: (debtId: number | string, data: DebtPaymentFormData) =>
        api.post<Transaction, DebtPaymentFormData>(`${ENDPOINT}/${debtId}/payment`, data),

    collectPayment: (debtId: number | string, data: DebtPaymentFormData) =>
        api.post<Transaction, DebtPaymentFormData>(`${ENDPOINT}/${debtId}/collect`, data),

    reopen: (id: number | string) =>
        api.post<Debt, {}>(`${ENDPOINT}/${id}/reopen`, {}),

    getSummary: async (): Promise<DebtSummary> => {
        const response = await apiClient.get(`${ENDPOINT}-summary`)
        return response.data
    },
}
