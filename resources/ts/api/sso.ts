import { api } from './client'
import type {
    IdentityProvider,
    IdentityProviderFormData,
    PublicProvider,
    SsoExchangeResponse,
    SsoPresetCatalogEntry,
} from '@/types/sso'

const ENDPOINT = '/identity-providers'

export const ssoApi = {
    // Public
    providers: () => api.get<PublicProvider[]>('/auth/sso/providers'),
    exchange: (ticket: string) => api.post<SsoExchangeResponse>('/auth/sso/exchange', { ticket }),

    // Admin
    presets: () => api.get<SsoPresetCatalogEntry[]>('/auth/sso/presets'),
    list: () => api.get<IdentityProvider[]>(ENDPOINT),
    getById: (id: number | string) => api.get<IdentityProvider>(`${ENDPOINT}/${id}`),
    create: (data: IdentityProviderFormData) => api.post<IdentityProvider, IdentityProviderFormData>(ENDPOINT, data),
    update: (id: number | string, data: Partial<IdentityProviderFormData>) =>
        api.patch<IdentityProvider, Partial<IdentityProviderFormData>>(`${ENDPOINT}/${id}`, data),
    delete: (id: number | string) => api.delete<void>(`${ENDPOINT}/${id}`),
    test: (id: number | string) => api.post<{ status: string; message?: string }>(`${ENDPOINT}/${id}/test`),
}

/**
 * Full backend URL for the browser-driven authorization redirect. Used with
 * window.location (NOT axios) since SSO is a top-level navigation.
 */
export const ssoRedirectUrl = (slug: string, redirect?: string): string => {
    const base = import.meta.env.VITE_API_URL || '/api'
    const query = redirect ? `?redirect=${encodeURIComponent(redirect)}` : ''

    return `${base}/auth/sso/${slug}/redirect${query}`
}
