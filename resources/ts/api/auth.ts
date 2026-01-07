import { apiClient } from './client'
import {
    AuthResponse,
    AuthStatus,
    LoginCredentials,
    RegisterData,
    User,
    TwoFactorAuthResponse,
    TwoFactorStatus,
    TwoFactorEnableResponse,
    TwoFactorConfirmResponse,
    TwoFactorRecoveryCodesResponse,
    TwoFactorRegenerateResponse,
} from '@/types'

export const authApi = {
    status: async (): Promise<AuthStatus> => {
        const response = await apiClient.get('/auth/status')
        return response.data
    },

    login: async (credentials: LoginCredentials): Promise<AuthResponse | TwoFactorAuthResponse> => {
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

    // 2FA Methods
    twoFactorStatus: async (): Promise<TwoFactorStatus> => {
        const response = await apiClient.get('/auth/2fa/status')
        return response.data
    },

    twoFactorEnable: async (): Promise<TwoFactorEnableResponse> => {
        const response = await apiClient.post('/auth/2fa/enable')
        return response.data
    },

    twoFactorConfirm: async (code: string): Promise<TwoFactorConfirmResponse> => {
        const response = await apiClient.post('/auth/2fa/confirm', { code })
        return response.data
    },

    twoFactorDisable: async (code: string): Promise<{ message: string }> => {
        const response = await apiClient.post('/auth/2fa/disable', { code })
        return response.data
    },

    twoFactorVerify: async (twoFactorToken: string, code: string): Promise<AuthResponse> => {
        const response = await apiClient.post('/auth/2fa/verify', {
            two_factor_token: twoFactorToken,
            code,
        })
        return response.data
    },

    twoFactorRecoveryCodes: async (): Promise<TwoFactorRecoveryCodesResponse> => {
        const response = await apiClient.get('/auth/2fa/recovery-codes')
        return response.data
    },

    twoFactorRegenerateRecoveryCodes: async (code: string): Promise<TwoFactorRegenerateResponse> => {
        const response = await apiClient.post('/auth/2fa/recovery-codes/regenerate', { code })
        return response.data
    },
}
