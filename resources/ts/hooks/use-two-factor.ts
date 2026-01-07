import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { authApi } from '@/api'
import { toast } from 'sonner'

const QUERY_KEY = ['two-factor-status']

export function useTwoFactorStatus() {
    return useQuery({
        queryKey: QUERY_KEY,
        queryFn: authApi.twoFactorStatus,
    })
}

export function useEnableTwoFactor() {
    return useMutation({
        mutationFn: authApi.twoFactorEnable,
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to enable 2FA')
        },
    })
}

export function useConfirmTwoFactor() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (code: string) => authApi.twoFactorConfirm(code),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Two-factor authentication enabled')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Invalid verification code')
        },
    })
}

export function useDisableTwoFactor() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (code: string) => authApi.twoFactorDisable(code),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Two-factor authentication disabled')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Invalid verification code')
        },
    })
}

export function useVerifyTwoFactor() {
    return useMutation({
        mutationFn: ({ token, code }: { token: string; code: string }) =>
            authApi.twoFactorVerify(token, code),
        onError: (error: Error) => {
            toast.error(error.message || 'Invalid verification code')
        },
    })
}

export function useRecoveryCodes() {
    return useQuery({
        queryKey: [...QUERY_KEY, 'recovery-codes'],
        queryFn: authApi.twoFactorRecoveryCodes,
    })
}

export function useRegenerateRecoveryCodes() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (code: string) => authApi.twoFactorRegenerateRecoveryCodes(code),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Recovery codes regenerated')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Invalid verification code')
        },
    })
}
