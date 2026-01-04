import { api } from './client'
import { Budget, BudgetFormData } from '@/types'

const ENDPOINT = '/budgets'

export const budgetsApi = {
    getAll: () =>
        api.get<Budget[]>(ENDPOINT),

    getById: (id: number | string) =>
        api.get<Budget>(`${ENDPOINT}/${id}`),

    create: (data: BudgetFormData) =>
        api.post<Budget, BudgetFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<BudgetFormData>) =>
        api.patch<Budget, Partial<BudgetFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) =>
        api.delete<void>(`${ENDPOINT}/${id}`),
}
