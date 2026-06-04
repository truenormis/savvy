import { CSSProperties } from 'react'
import { siGithub, siGoogle, siOkta, siGitlab, siKeycloak, siAuthentik } from 'simple-icons'
import microsoftLogo from '@/assets/brands/microsoft.svg'
import { cn } from '@/lib/utils'
import { presetIcon } from './presetMeta'

interface SimpleIconData {
    path: string
    hex: string
    title: string
}

const SIMPLE_ICONS: Record<string, SimpleIconData> = {
    github: siGithub,
    google: siGoogle,
    okta: siOkta,
    gitlab: siGitlab,
    keycloak: siKeycloak,
    authentik: siAuthentik,
}

const MONOCHROME = new Set(['github'])

const BRAND_ACCENT: Record<string, string> = {
    entra: '#0078D4',
    github: '#8b949e',
    google: '#4285F4',
    okta: `#${siOkta.hex}`,
    gitlab: `#${siGitlab.hex}`,
    keycloak: `#${siKeycloak.hex}`,
    authentik: `#${siAuthentik.hex}`,
}

export function brandAccent(preset: string): string | null {
    return BRAND_ACCENT[preset] ?? null
}

export function brandVars(preset: string): CSSProperties {
    return { ['--brand' as string]: brandAccent(preset) ?? 'var(--muted-foreground)' }
}

const tileTint: CSSProperties = {
    backgroundColor: 'color-mix(in oklab, var(--brand) 10%, var(--background))',
    borderColor: 'color-mix(in oklab, var(--brand) 30%, var(--border))',
}

interface BrandTileProps {
    preset: string
    className?: string
    iconClassName?: string
}

export function BrandTile({ preset, className, iconClassName }: BrandTileProps) {
    return (
        <div
            style={{ ...brandVars(preset), ...tileTint }}
            className={cn('flex items-center justify-center rounded-xl border', className)}
        >
            <BrandIcon preset={preset} className={iconClassName} />
        </div>
    )
}

interface BrandIconProps {
    preset: string
    className?: string
}

export function BrandIcon({ preset, className }: BrandIconProps) {
    if (preset === 'entra') {
        return <img src={microsoftLogo} className={className} alt="Microsoft" />
    }

    const icon = SIMPLE_ICONS[preset]
    if (icon) {
        const mono = MONOCHROME.has(preset)
        return (
            <svg
                role="img"
                viewBox="0 0 24 24"
                className={className}
                fill={mono ? 'currentColor' : `#${icon.hex}`}
                aria-label={icon.title}
            >
                <path d={icon.path} />
            </svg>
        )
    }

    const Fallback = presetIcon(preset)
    return <Fallback className={className} />
}
