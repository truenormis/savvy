import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { currenciesApi } from '@/api'
import { CurrencyFormData } from '@/types'
import { toast } from 'sonner'

const QUERY_KEY = ['currencies']

export function useCurrencies() {
    return useQuery({
        queryKey: QUERY_KEY,
        queryFn: currenciesApi.getAll,
    })
}

export function useCurrency(id: string | number) {
    return useQuery({
        queryKey: [...QUERY_KEY, id],
        queryFn: () => currenciesApi.getById(id),
        enabled: !!id,
    })
}

export function useCreateCurrency(redirectTo?: string) {
    const queryClient = useQueryClient()
    const navigate = useNavigate()

    return useMutation({
        mutationFn: (data: CurrencyFormData) => currenciesApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Currency created')
            if (redirectTo) navigate(redirectTo)
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to create currency')
        },
    })
}

export function useUpdateCurrency(redirectTo?: string) {
    const queryClient = useQueryClient()
    const navigate = useNavigate()

    return useMutation({
        mutationFn: ({ id, data }: { id: string | number; data: Partial<CurrencyFormData> }) =>
            currenciesApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Currency updated')
            if (redirectTo) navigate(redirectTo)
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to update currency')
        },
    })
}

export function useDeleteCurrency() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (id: string | number) => currenciesApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Currency deleted')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to delete currency')
        },
    })
}

export function useSetBaseCurrency() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (id: string | number) => currenciesApi.setBase(id),
        onSuccess: () => {
            // Invalidate all related queries since rates changed
            queryClient.invalidateQueries()
            toast.success('Base currency updated')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to set base currency')
        },
    })
}
