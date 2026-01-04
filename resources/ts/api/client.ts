import axios, { AxiosInstance, AxiosRequestConfig } from 'axios'
import { toast } from 'sonner'
import { ApiError } from '@/types'

interface WrappedResponse<T> {
    data: T
    message?: string
    meta?: {
        total?: number
        page?: number
        limit?: number
    }
}

const createApiClient = (baseURL: string): AxiosInstance => {
    const client = axios.create({
        baseURL,
        timeout: 10000,
        headers: {
            'Content-Type': 'application/json',
        },
    })

    client.interceptors.request.use((config) => {
        const token = localStorage.getItem('auth_token')
        if (token) {
            config.headers.Authorization = `Bearer ${token}`
        }
        return config
    })

    client.interceptors.response.use(
        (response) => response,
        (error) => {
            const message = error.response?.data?.message || 'An error occurred'

            // Handle 401 - clear token and redirect to login
            if (error.response?.status === 401) {
                localStorage.removeItem('auth_token')
                // Only redirect if not already on login page
                if (!window.location.pathname.includes('/login')) {
                    window.location.href = '/login'
                }
                return Promise.reject(error)
            }

            // Show toast for non-validation errors
            if (error.response?.status !== 422) {
                toast.error(message)
            }

            const apiError: ApiError = {
                message,
                code: error.response?.status?.toString() || 'UNKNOWN',
                details: error.response?.data?.details,
            }
            return Promise.reject(apiError)
        }
    )

    return client
}

export const apiClient = createApiClient(
    import.meta.env.VITE_API_URL || '/api'
)

// Helper to unwrap response
const unwrap = <T>(response: WrappedResponse<T> | T): T => {
    if (response && typeof response === 'object' && 'data' in response) {
        return (response as WrappedResponse<T>).data
    }
    return response as T
}

export const api = {
    get: <T>(url: string, config?: AxiosRequestConfig) =>
        apiClient.get<WrappedResponse<T> | T>(url, config)
            .then((res) => unwrap<T>(res.data)),

    post: <T, D = unknown>(url: string, data?: D, config?: AxiosRequestConfig) =>
        apiClient.post<WrappedResponse<T> | T>(url, data, config)
            .then((res) => unwrap<T>(res.data)),

    put: <T, D = unknown>(url: string, data?: D, config?: AxiosRequestConfig) =>
        apiClient.put<WrappedResponse<T> | T>(url, data, config)
            .then((res) => unwrap<T>(res.data)),

    patch: <T, D = unknown>(url: string, data?: D, config?: AxiosRequestConfig) =>
        apiClient.patch<WrappedResponse<T> | T>(url, data, config)
            .then((res) => unwrap<T>(res.data)),

    delete: <T>(url: string, config?: AxiosRequestConfig) =>
        apiClient.delete<WrappedResponse<T> | T>(url, config)
            .then((res) => unwrap<T>(res.data)),
}
