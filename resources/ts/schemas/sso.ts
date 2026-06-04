import { z } from 'zod'

export const roleMappingRuleSchema = z.object({
    claim: z.string().min(1, 'Claim is required'),
    operator: z.enum(['equals', 'contains', 'one_of']),
    value: z.string().min(1, 'Value is required'),
    role: z.enum(['admin', 'read-write', 'read-only']),
})

export const identityProviderSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    slug: z
        .string()
        .min(1, 'Slug is required')
        .max(255)
        .regex(/^[a-z0-9-]+$/, 'Use lowercase letters, digits and hyphens'),
    preset: z.string().min(1, 'Pick a provider type'),
    enabled: z.boolean().default(false),
    fields: z.record(z.string(), z.string()).default({}),
    role_mapping: z.array(roleMappingRuleSchema).default([]),
    default_role: z.enum(['read-write', 'read-only']).default('read-only'),
    allow_jit: z.boolean().default(true),
    sync_role_on_login: z.boolean().default(false),
    link_by_email: z.boolean().default(true),
})

export type IdentityProviderFormValues = z.infer<typeof identityProviderSchema>
