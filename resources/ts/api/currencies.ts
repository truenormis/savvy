import { api } from './client'
import { Currency, CurrencyFormData } from '@/types'

const ENDPOINT = '/currencies'

export const currenciesApi = {
    getAll: () =>
        api.get<Currency[]>(ENDPOINT),

    getById: (id: number | string) =>
        api.get<Currency>(`${ENDPOINT}/${id}`),

    create: (data: CurrencyFormData) =>
        api.post<Currency, CurrencyFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<CurrencyFormData>) =>
        api.patch<Currency, Partial<CurrencyFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) =>
        api.delete<void>(`${ENDPOINT}/${id}`),

    setBase: (id: number | string) =>
        api.post<Currency>(`${ENDPOINT}/${id}/set-base`),
}
