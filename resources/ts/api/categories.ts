import { api, apiClient } from './client'
import { Category, CategoryFormData, CategorySummaryResponse } from '@/types'

const ENDPOINT = '/categories'

export const categoriesApi = {
    getAll: () =>
        api.get<Category[]>(ENDPOINT),

    getById: (id: number | string) =>
        api.get<Category>(`${ENDPOINT}/${id}`),

    create: (data: CategoryFormData) =>
        api.post<Category, CategoryFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<CategoryFormData>) =>
        api.patch<Category, Partial<CategoryFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) =>
        api.delete<void>(`${ENDPOINT}/${id}`),

    getByType: (type: 'income' | 'expense') =>
        api.get<Category[]>(`${ENDPOINT}?type=${type}`),

    getSummary: async (params: {
        type: 'income' | 'expense'
        start_date?: string
        end_date?: string
    }): Promise<CategorySummaryResponse> => {
        const searchParams = new URLSearchParams()
        searchParams.set('type', params.type)
        if (params.start_date) searchParams.set('start_date', params.start_date)
        if (params.end_date) searchParams.set('end_date', params.end_date)
        const response = await apiClient.get(`${ENDPOINT}-summary?${searchParams.toString()}`)
        return response.data
    },
}
