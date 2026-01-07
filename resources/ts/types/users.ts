export type UserRole = 'admin' | 'read-write' | 'read-only'

export interface User {
    id: number
    name: string
    email: string
    role: UserRole
    createdAt: string
}

export interface UserFormData {
    name: string
    email: string
    password?: string
    role?: UserRole
}
