import { create } from 'zustand'
import { startAuthentication } from '@simplewebauthn/browser'
import { authApi, webauthnApi } from '@/api'
import { setOnUnauthorized } from '@/api/client'
import { User, LoginCredentials, RegisterData, AuthResponse, TwoFactorAuthResponse } from '@/types'

export type LoginResult =
    | { success: true }
    | { success: false; requires_2fa: true; two_factor_token: string }

interface AuthState {
    user: User | null
    isLoading: boolean
    isAuthenticated: boolean

    login: (credentials: LoginCredentials) => Promise<LoginResult>
    loginWith2FA: (twoFactorToken: string, code: string) => Promise<void>
    loginWithPasskey: (options?: { twoFactorToken?: string; useAutofill?: boolean }) => Promise<void>
    register: (data: RegisterData) => Promise<void>
    logout: () => Promise<void>
    checkAuth: () => Promise<void>
    setUser: (user: User) => void
    clear: () => void
}

function isTwoFactorResponse(response: AuthResponse | TwoFactorAuthResponse): response is TwoFactorAuthResponse {
    return 'requires_2fa' in response && response.requires_2fa === true
}

export const useAuthStore = create<AuthState>((set, get) => ({
    user: null,
    isLoading: true,
    isAuthenticated: false,

    login: async (credentials) => {
        const response = await authApi.login(credentials)

        if (isTwoFactorResponse(response)) {
            return { success: false, requires_2fa: true, two_factor_token: response.two_factor_token }
        }

        set({ user: response.user, isAuthenticated: true })
        return { success: true }
    },

    loginWith2FA: async (twoFactorToken, code) => {
        const { user } = await authApi.twoFactorVerify(twoFactorToken, code)
        set({ user, isAuthenticated: true })
    },

    loginWithPasskey: async ({ twoFactorToken, useAutofill } = {}) => {
        const { token, options } = await webauthnApi.loginOptions(twoFactorToken)
        const assertion = await startAuthentication({
            optionsJSON: options,
            useBrowserAutofill: useAutofill ?? false,
        })
        const { user } = await webauthnApi.loginVerify(token, assertion, twoFactorToken)
        set({ user, isAuthenticated: true })
    },

    register: async (data) => {
        const { user } = await authApi.register(data)
        sessionStorage.setItem('just_registered', 'true')
        set({ user, isAuthenticated: true })
    },

    logout: async () => {
        try {
            await authApi.logout()
        } catch {
            // session may already be gone; clear locally regardless
        }
        get().clear()
    },

    checkAuth: async () => {
        try {
            const { user } = await authApi.me()
            set({ user, isAuthenticated: user !== null, isLoading: false })
        } catch {
            set({ user: null, isAuthenticated: false, isLoading: false })
        }
    },

    setUser: (user) => set({ user, isAuthenticated: true, isLoading: false }),

    clear: () => set({ user: null, isAuthenticated: false, isLoading: false }),
}))

// A 401 anywhere drops auth state; AuthProvider then soft-redirects to /login.
setOnUnauthorized(() => useAuthStore.getState().clear())

export const useUser = () => useAuthStore((state) => state.user)
export const useIsAuthenticated = () => useAuthStore((state) => state.isAuthenticated)
export const useAuthLoading = () => useAuthStore((state) => state.isLoading)
