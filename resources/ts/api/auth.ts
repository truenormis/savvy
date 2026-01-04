import { apiClient } from './client'
import { AuthResponse, AuthStatus, LoginCredentials, RegisterData, User } from '@/types'

export const authApi = {
    status: async (): Promise<AuthStatus> => {
        const response = await apiClient.get('/auth/status')
        return response.data
    },

    login: async (credentials: LoginCredentials): Promise<AuthResponse> => {
        const response = await apiClient.post('/auth/login', credentials)
        return response.data
    },

    register: async (data: RegisterData): Promise<AuthResponse> => {
        const response = await apiClient.post('/auth/register', data)
        return response.data
    },

    me: async (): Promise<{ user: User }> => {
        const response = await apiClient.get('/auth/me')
        return response.data
    },

    refresh: async (): Promise<{ token: string }> => {
        const response = await apiClient.post('/auth/refresh')
        return response.data
    },
}
