import { api } from './client'
import { RecurringTransaction, RecurringFormData } from '@/types'

const ENDPOINT = '/recurring'

export const recurringApi = {
    getAll: () =>
        api.get<RecurringTransaction[]>(ENDPOINT),

    getById: (id: number | string) =>
        api.get<RecurringTransaction>(`${ENDPOINT}/${id}`),

    create: (data: RecurringFormData) =>
        api.post<RecurringTransaction, RecurringFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<RecurringFormData>) =>
        api.patch<RecurringTransaction, Partial<RecurringFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) =>
        api.delete<void>(`${ENDPOINT}/${id}`),

    skip: (id: number | string) =>
        api.post<RecurringTransaction>(`${ENDPOINT}/${id}/skip`),

    getUpcoming: () =>
        api.get<RecurringTransaction[]>('/recurring-upcoming'),
}
