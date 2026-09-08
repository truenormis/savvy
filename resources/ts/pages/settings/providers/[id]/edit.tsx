import { useParams } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import { FormPage } from '@/components/shared'
import { IdentityProviderForm, SelectedPresetBanner } from '@/components/features/sso'
import { useIdentityProvider, useUpdateIdentityProvider } from '@/hooks/use-sso'
import type { IdentityProviderFormValues } from '@/schemas/sso'

function toStringFields(config: Record<string, unknown>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(config ?? {}).map(([key, value]) => [
            key,
            Array.isArray(value) ? value.join(' ') : value == null ? '' : String(value),
        ]),
    )
}

export default function ProviderEditPage() {
    const { id } = useParams<{ id: string }>()
    const { data: provider, isLoading } = useIdentityProvider(id!)
    const updateProvider = useUpdateIdentityProvider('/settings/providers')

    if (isLoading || !provider) {
        return (
            <div className="flex items-center justify-center min-h-[200px]">
                <Loader2 className="size-8 animate-spin text-muted-foreground" />
            </div>
        )
    }

    const defaultValues: Partial<IdentityProviderFormValues> = {
        name: provider.name,
        slug: provider.slug,
        preset: provider.preset,
        enabled: provider.enabled,
        fields: toStringFields(provider.config),
        role_mapping: provider.roleMapping ?? [],
        default_role: provider.defaultRole === 'read-write' ? 'read-write' : 'read-only',
        allow_jit: provider.allowJit,
        sync_role_on_login: provider.syncRoleOnLogin,
        link_by_email: provider.linkByEmail,
    }

    return (
        <FormPage title="Edit Identity Provider" backLink="/settings/providers">
            <div className="space-y-6">
                <SelectedPresetBanner preset={provider.preset} />

                <IdentityProviderForm
                    isEdit
                    presetLocked
                    previewPreset={provider.preset}
                    defaultValues={defaultValues}
                    onSubmit={(data) => updateProvider.mutate({ id: provider.id, data })}
                    isSubmitting={updateProvider.isPending}
                    submitLabel="Save"
                />
            </div>
        </FormPage>
    )
}
