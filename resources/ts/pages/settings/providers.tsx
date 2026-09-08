import { Page, PageHeader } from '@/components/shared'
import { ProviderCard, ProvidersEmptyState, ProviderGridSkeleton } from '@/components/features/sso'
import { useIdentityProviders, useDeleteIdentityProvider, useTestIdentityProvider } from '@/hooks/use-sso'

export default function ProvidersPage() {
    const { data: providers, isLoading } = useIdentityProviders()
    const deleteProvider = useDeleteIdentityProvider()
    const testProvider = useTestIdentityProvider()

    const hasProviders = !!providers?.length

    return (
        <Page title="Identity Providers">
            <PageHeader
                title="Identity Providers"
                description="Configure OIDC and SAML single sign-on"
                createLink={hasProviders ? '/settings/providers/create' : undefined}
                createLabel="Add Provider"
            />

            {isLoading ? (
                <ProviderGridSkeleton />
            ) : !hasProviders ? (
                <ProvidersEmptyState />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {providers.map((provider, index) => (
                        <ProviderCard
                            key={provider.id}
                            provider={provider}
                            index={index}
                            onDelete={(id) => deleteProvider.mutate(id)}
                            onTest={(id) => testProvider.mutate(id)}
                        />
                    ))}
                </div>
            )}
        </Page>
    )
}
