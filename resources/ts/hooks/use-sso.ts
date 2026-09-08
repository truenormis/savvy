import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { ssoApi } from '@/api/sso'
import type { IdentityProviderFormData } from '@/types/sso'

const QUERY_KEY = ['identity-providers']
const PRESETS_KEY = ['sso-presets']
const PUBLIC_KEY = ['sso-providers']

export function useSsoProviders() {
    return useQuery({
        queryKey: PUBLIC_KEY,
        queryFn: () => ssoApi.providers(),
        staleTime: 60_000,
    })
}

export function useSsoPresets() {
    return useQuery({
        queryKey: PRESETS_KEY,
        queryFn: () => ssoApi.presets(),
        staleTime: Infinity,
    })
}

export function useIdentityProviders() {
    return useQuery({
        queryKey: QUERY_KEY,
        queryFn: () => ssoApi.list(),
    })
}

export function useIdentityProvider(id: string | number) {
    return useQuery({
        queryKey: [...QUERY_KEY, id],
        queryFn: () => ssoApi.getById(id),
        enabled: !!id,
    })
}

export function useCreateIdentityProvider(redirectTo?: string) {
    const queryClient = useQueryClient()
    const navigate = useNavigate()

    return useMutation({
        mutationFn: (data: IdentityProviderFormData) => ssoApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Identity provider created')
            if (redirectTo) navigate(redirectTo)
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to create provider')
        },
    })
}

export function useUpdateIdentityProvider(redirectTo?: string) {
    const queryClient = useQueryClient()
    const navigate = useNavigate()

    return useMutation({
        mutationFn: ({ id, data }: { id: string | number; data: Partial<IdentityProviderFormData> }) =>
            ssoApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Identity provider updated')
            if (redirectTo) navigate(redirectTo)
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to update provider')
        },
    })
}

export function useDeleteIdentityProvider() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (id: string | number) => ssoApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Identity provider deleted')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to delete provider')
        },
    })
}

export function useTestIdentityProvider() {
    return useMutation({
        mutationFn: (id: string | number) => ssoApi.test(id),
        onSuccess: (result) => {
            if (result.status === 'ok') {
                toast.success('Connection looks good')
            } else {
                toast.error(result.message || 'Connection test failed')
            }
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Connection test failed')
        },
    })
}
