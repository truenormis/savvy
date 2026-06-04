import { Separator } from '@/components/ui/separator'
import { useSsoProviders } from '@/hooks/use-sso'
import { ssoRedirectUrl } from '@/api/sso'
import { BrandIcon, brandVars } from './BrandIcon'

export function SsoButtons({ showDivider = true }: { showDivider?: boolean }) {
    const { data: providers } = useSsoProviders()

    if (!providers || providers.length === 0) {
        return null
    }

    return (
        <div className="space-y-4">
            <div className="grid gap-2">
                {providers.map((provider) => (
                    <button
                        key={provider.slug}
                        type="button"
                        style={brandVars(provider.preset)}
                        onClick={() => {
                            window.location.href = ssoRedirectUrl(provider.slug)
                        }}
                        className="group inline-flex h-10 w-full items-center justify-center gap-2.5 rounded-md border bg-background px-4 text-sm font-medium shadow-sm transition-all hover:-translate-y-px hover:border-[color-mix(in_oklab,var(--brand)_55%,var(--border))] hover:bg-[color-mix(in_oklab,var(--brand)_6%,var(--background))] hover:shadow-[0_4px_16px_-6px_color-mix(in_oklab,var(--brand)_60%,transparent)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring active:translate-y-0"
                    >
                        <BrandIcon preset={provider.preset} className="size-4 transition-transform group-hover:scale-110" />
                        Continue with {provider.name}
                    </button>
                ))}
            </div>

            {showDivider && (
                <div className="relative">
                    <Separator />
                    <span className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-card px-2 text-xs text-muted-foreground">
                        or
                    </span>
                </div>
            )}
        </div>
    )
}
