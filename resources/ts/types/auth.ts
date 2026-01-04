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

export interface AuthStatus {
    needs_registration: boolean
}
