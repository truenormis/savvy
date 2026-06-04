import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useSsoPresets } from '@/hooks/use-sso'
import { BrandTile } from './BrandIcon'

interface SelectedPresetBannerProps {
    preset: string
    onChange?: () => void
}

export function SelectedPresetBanner({ preset, onChange }: SelectedPresetBannerProps) {
    const { data: presets } = useSsoPresets()
    const selected = presets?.find((p) => p.key === preset)

    return (
        <div className="flex items-center justify-between rounded-lg border bg-muted/40 p-3">
            <div className="flex items-center gap-3">
                <BrandTile preset={preset} className="size-9 rounded-lg" iconClassName="size-5" />
                <div>
                    <p className="text-sm font-medium">{selected?.label}</p>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">{selected?.protocol}</p>
                </div>
            </div>
            {onChange && (
                <Button type="button" variant="ghost" size="sm" onClick={onChange}>
                    <ArrowLeft className="mr-2 size-4" />
                    Change
                </Button>
            )}
        </div>
    )
}
