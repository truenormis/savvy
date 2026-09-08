import type { User, UserRole } from '@/types/users'

export type SsoProtocol = 'oidc' | 'oauth2' | 'saml'

export type RoleMappingOperator = 'equals' | 'contains' | 'one_of'

export interface RoleMappingRule {
    claim: string
    operator: RoleMappingOperator
    value: string
    role: UserRole
}

export interface IdentityProvider {
    id: number
    name: string
    slug: string
    protocol: SsoProtocol
    preset: string
    enabled: boolean
    sortOrder: number
    config: Record<string, unknown>
    claimMappings: Record<string, string | null> | null
    roleMapping: RoleMappingRule[] | null
    defaultRole: UserRole
    allowJit: boolean
    syncRoleOnLogin: boolean
    linkByEmail: boolean
    hasClientSecret: boolean
    metadataUrl: string
    callbackUrl: string
    acsUrl: string
    createdAt: string
    updatedAt: string
}

export interface PublicProvider {
    slug: string
    name: string
    preset: string
    protocol: SsoProtocol
}

export interface PresetField {
    key: string
    label: string
    type: 'text' | 'password' | 'url' | 'textarea'
    required?: boolean
    secret?: boolean
    group: 'config' | 'secrets'
    placeholder?: string
    help?: string
}

export interface SsoPresetCatalogEntry {
    key: string
    label: string
    protocol: SsoProtocol
    fields: PresetField[]
    default_claim_mappings: Record<string, string | null>
}

export interface IdentityProviderFormData {
    name: string
    slug: string
    preset: string
    enabled: boolean
    sort_order?: number
    fields: Record<string, string>
    role_mapping?: RoleMappingRule[]
    default_role?: UserRole
    allow_jit?: boolean
    sync_role_on_login?: boolean
    link_by_email?: boolean
}

export type SsoExchangeResponse =
    | { user: User }
    | { requires_2fa: true; two_factor_token: string }
