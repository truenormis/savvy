import { api } from './client'
import type { User, UserFormData } from '@/types/users'

const ENDPOINT = '/users'

export const usersApi = {
    getAll: () => api.get<User[]>(ENDPOINT),

    getById: (id: number | string) => api.get<User>(`${ENDPOINT}/${id}`),

    create: (data: UserFormData) => api.post<User, UserFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<UserFormData>) =>
        api.patch<User, Partial<UserFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) => api.delete<void>(`${ENDPOINT}/${id}`),
}
