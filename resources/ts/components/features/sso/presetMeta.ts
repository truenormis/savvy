import {
    Github,
    Globe,
    Cloud,
    ShieldCheck,
    GitBranch,
    KeyRound,
    Fingerprint,
    Network,
    KeySquare,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

// The frontend owns provider visuals; the backend only supplies the preset key.
const PRESET_ICONS: Record<string, LucideIcon> = {
    entra: Cloud,
    github: Github,
    google: Globe,
    okta: ShieldCheck,
    gitlab: GitBranch,
    keycloak: KeyRound,
    authentik: Fingerprint,
    custom_oidc: Network,
    custom_saml: KeySquare,
}

export function presetIcon(preset: string): LucideIcon {
    return PRESET_ICONS[preset] ?? KeyRound
}

export function isCustomPreset(preset: string): boolean {
    return preset === 'custom_oidc' || preset === 'custom_saml'
}
