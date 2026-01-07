export interface User {
    id: number
    name: string
    email: string
}

export interface LoginCredentials {
    email: string
    password: string
}

export interface RegisterData {
    name: string
    email: string
    password: string
}

export interface AuthResponse {
    user: User
    token: string
}

export interface TwoFactorAuthResponse {
    requires_2fa: true
    two_factor_token: string
}

export interface AuthStatus {
    needs_registration: boolean
}

// 2FA Types
export interface TwoFactorStatus {
    enabled: boolean
    pending_confirmation: boolean
    recovery_codes_remaining: number | null
}

export interface TwoFactorEnableResponse {
    secret: string
    qr_code_url: string
}

export interface TwoFactorConfirmResponse {
    message: string
    recovery_codes: string[]
}

export interface TwoFactorRecoveryCodesResponse {
    remaining_count: number
}

export interface TwoFactorRegenerateResponse {
    message: string
    recovery_codes: string[]
}
