import { useState } from 'react'
import { FormPage } from '@/components/shared'
import { IdentityProviderForm, PresetChooser, SelectedPresetBanner } from '@/components/features/sso'
import { useCreateIdentityProvider } from '@/hooks/use-sso'

export default function ProviderCreatePage() {
    const [preset, setPreset] = useState<string | null>(null)
    const createProvider = useCreateIdentityProvider('/settings/providers')

    if (!preset) {
        return (
            <FormPage title="Add Identity Provider" backLink="/settings/providers">
                <PresetChooser onSelect={setPreset} />
            </FormPage>
        )
    }

    return (
        <FormPage title="Add Identity Provider" backLink="/settings/providers">
            <div className="space-y-6">
                <SelectedPresetBanner preset={preset} onChange={() => setPreset(null)} />

                <IdentityProviderForm
                    presetLocked
                    previewPreset={preset}
                    defaultValues={{ preset }}
                    onSubmit={(data) => createProvider.mutate(data)}
                    isSubmitting={createProvider.isPending}
                    submitLabel="Create"
                />
            </div>
        </FormPage>
    )
}
