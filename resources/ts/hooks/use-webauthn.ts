import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { startRegistration } from '@simplewebauthn/browser'
import { webauthnApi } from '@/api'
import { toast } from 'sonner'

const QUERY_KEY = ['webauthn-credentials']

export function isPasskeyDomainSupported(): boolean {
    if (typeof window === 'undefined' || !window.isSecureContext) return false

    const host = window.location.hostname
    if (host === 'localhost') return true

    const isIpv4 = /^\d{1,3}(\.\d{1,3}){3}$/.test(host)
    const isIpv6 = host.includes(':')
    return !isIpv4 && !isIpv6
}

export function useWebauthnCredentials() {
    return useQuery({
        queryKey: QUERY_KEY,
        queryFn: webauthnApi.listCredentials,
    })
}

export function useRegisterPasskey() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: async (name: string | null) => {
            const { token, options } = await webauthnApi.registerOptions()
            const attestation = await startRegistration({ optionsJSON: options })
            return webauthnApi.registerVerify(token, attestation, name)
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Passkey added')
        },
        onError: (error: unknown) => {
            toast.error(passkeyErrorMessage(error, 'Failed to add passkey'))
        },
    })
}

export function useRenamePasskey() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: ({ id, name }: { id: number; name: string }) =>
            webauthnApi.renameCredential(id, name),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Passkey renamed')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to rename passkey')
        },
    })
}

export function useDeletePasskey() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (id: number) => webauthnApi.deleteCredential(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Passkey removed')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to remove passkey')
        },
    })
}

export function passkeyErrorMessage(error: unknown, fallback: string): string {
    if (error instanceof Error) {
        if (error.name === 'NotAllowedError' || error.name === 'AbortError') {
            return 'Passkey prompt was cancelled'
        }
        if (error.name === 'InvalidStateError') {
            return 'This device already has a passkey for this account'
        }
        return error.message || fallback
    }
    if (error && typeof error === 'object' && 'message' in error) {
        return (error as { message: string }).message
    }
    return fallback
}
