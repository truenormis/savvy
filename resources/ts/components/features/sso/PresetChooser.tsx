import { CSSProperties } from 'react'
import { Loader2, ChevronRight } from 'lucide-react'
import { useSsoPresets } from '@/hooks/use-sso'
import { isCustomPreset } from './presetMeta'
import { BrandIcon, brandAccent } from './BrandIcon'
import type { SsoPresetCatalogEntry } from '@/types/sso'

interface PresetChooserProps {
    onSelect: (preset: string) => void
}

function PresetCard({ preset, onSelect }: { preset: SsoPresetCatalogEntry; onSelect: (key: string) => void }) {
    const brand: CSSProperties & { '--brand': string } = {
        '--brand': brandAccent(preset.key) ?? 'var(--muted-foreground)',
    }

    return (
        <button
            type="button"
            onClick={() => onSelect(preset.key)}
            style={brand}
            className="group relative flex items-center gap-4 rounded-xl border bg-card p-4 text-left transition-all hover:-translate-y-0.5 hover:border-[color-mix(in_oklab,var(--brand)_45%,var(--border))] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <div
                style={{
                    backgroundColor: 'color-mix(in oklab, var(--brand) 10%, var(--background))',
                    borderColor: 'color-mix(in oklab, var(--brand) 30%, var(--border))',
                }}
                className="flex size-11 shrink-0 items-center justify-center rounded-lg border"
            >
                <BrandIcon preset={preset.key} className="size-5" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate font-medium">{preset.label}</p>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">{preset.protocol}</p>
            </div>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
        </button>
    )
}

export function PresetChooser({ onSelect }: PresetChooserProps) {
    const { data: presets, isLoading } = useSsoPresets()

    if (isLoading || !presets) {
        return (
            <div className="flex items-center justify-center min-h-[200px]">
                <Loader2 className="size-8 animate-spin text-muted-foreground" />
            </div>
        )
    }

    const builtIn = presets.filter((p) => !isCustomPreset(p.key))
    const custom = presets.filter((p) => isCustomPreset(p.key))

    return (
        <div className="max-w-3xl space-y-8">
            <section className="space-y-3">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold">Quick setup</span>
                    <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">Recommended</span>
                </div>
                <p className="-mt-1 text-sm text-muted-foreground">
                    Pick a provider and connect it in seconds — endpoints and scopes are pre-filled for you.
                </p>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {builtIn.map((preset) => (
                        <PresetCard key={preset.key} preset={preset} onSelect={onSelect} />
                    ))}
                </div>
            </section>

            <section className="space-y-3">
                <span className="text-sm font-semibold">Bring your own</span>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {custom.map((preset) => (
                        <PresetCard key={preset.key} preset={preset} onSelect={onSelect} />
                    ))}
                </div>
            </section>
        </div>
    )
}
