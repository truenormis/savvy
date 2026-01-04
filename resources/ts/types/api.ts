export interface BaseEntity {
    id: number
    createdAt?: string
    updatedAt?: string
}

export interface PaginatedResponse<T> {
    data: T[]
    total: number
    page: number
    limit: number
}

export interface ApiError {
    message: string
    code: string
    details?: Record<string, string[]>
}

export interface CrudConfig {
    endpoint: string
    queryKey: string[]
    redirectTo?: string
}

export type MutationData<T> = Omit<T, 'id' | 'createdAt' | 'updatedAt'>
