import { apiClient } from './client'
import type {
    RegistrationResponseJSON,
    AuthenticationResponseJSON,
} from '@simplewebauthn/browser'
import {
    AuthResponse,
    WebauthnCredentialSummary,
    WebauthnRegisterOptions,
    WebauthnLoginOptions,
} from '@/types'

export const webauthnApi = {
    listCredentials: async (): Promise<WebauthnCredentialSummary[]> => {
        const { data } = await apiClient.get('/auth/webauthn/credentials')
        return data.credentials
    },

    registerOptions: async (): Promise<WebauthnRegisterOptions> => {
        const { data } = await apiClient.post('/auth/webauthn/register/options')
        return data
    },

    registerVerify: async (
        token: string,
        response: RegistrationResponseJSON,
        name: string | null,
    ): Promise<{ credential: WebauthnCredentialSummary }> => {
        const { data } = await apiClient.post('/auth/webauthn/register/verify', {
            token,
            name,
            response,
        })
        return data
    },

    renameCredential: async (id: number, name: string): Promise<void> => {
        await apiClient.patch(`/auth/webauthn/credentials/${id}`, { name })
    },

    deleteCredential: async (id: number): Promise<void> => {
        await apiClient.delete(`/auth/webauthn/credentials/${id}`)
    },

    loginOptions: async (twoFactorToken?: string): Promise<WebauthnLoginOptions> => {
        const { data } = await apiClient.post('/auth/webauthn/login/options', {
            two_factor_token: twoFactorToken ?? null,
        })
        return data
    },

    loginVerify: async (
        token: string,
        response: AuthenticationResponseJSON,
        twoFactorToken?: string,
    ): Promise<AuthResponse> => {
        const { data } = await apiClient.post('/auth/webauthn/login/verify', {
            token,
            response,
            two_factor_token: twoFactorToken ?? null,
        })
        return data
    },
}
