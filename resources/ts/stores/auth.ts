import { create } from 'zustand'
import { authApi } from '@/api'
import { User, LoginCredentials, RegisterData } from '@/types'

const TOKEN_KEY = 'auth_token'

interface AuthState {
    user: User | null
    token: string | null
    isLoading: boolean
    isAuthenticated: boolean

    // Actions
    login: (credentials: LoginCredentials) => Promise<void>
    register: (data: RegisterData) => Promise<void>
    logout: () => void
    checkAuth: () => Promise<void>
    setToken: (token: string) => void
}

export const useAuthStore = create<AuthState>((set, get) => ({
    user: null,
    token: localStorage.getItem(TOKEN_KEY),
    isLoading: true,
    isAuthenticated: false,

    login: async (credentials) => {
        const { user, token } = await authApi.login(credentials)
        localStorage.setItem(TOKEN_KEY, token)
        set({ user, token, isAuthenticated: true })
    },

    register: async (data) => {
        const { user, token } = await authApi.register(data)
        localStorage.setItem(TOKEN_KEY, token)
        set({ user, token, isAuthenticated: true })
    },

    logout: () => {
        localStorage.removeItem(TOKEN_KEY)
        set({ user: null, token: null, isAuthenticated: false })
    },

    checkAuth: async () => {
        const token = get().token
        if (!token) {
            set({ isLoading: false, isAuthenticated: false })
            return
        }

        try {
            const { user } = await authApi.me()
            set({ user, isAuthenticated: true, isLoading: false })
        } catch {
            localStorage.removeItem(TOKEN_KEY)
            set({ user: null, token: null, isAuthenticated: false, isLoading: false })
        }
    },

    setToken: (token) => {
        localStorage.setItem(TOKEN_KEY, token)
        set({ token })
    },
}))

// Selector hooks
export const useUser = () => useAuthStore((state) => state.user)
export const useIsAuthenticated = () => useAuthStore((state) => state.isAuthenticated)
export const useAuthLoading = () => useAuthStore((state) => state.isLoading)
