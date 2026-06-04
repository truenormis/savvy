import { ReactNode } from 'react'
import { toast } from 'sonner'
import { Coins, ShieldCheck, Lock } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { Page, PageHeader, FormWrapper } from '@/components/shared'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useSettings, useUpdateSettings, useSsoProviders } from '@/hooks'

function Section({
    icon: Icon,
    title,
    description,
    children,
}: {
    icon: LucideIcon
    title: string
    description: string
    children: ReactNode
}) {
    return (
        <section className="rounded-xl border bg-card shadow-sm">
            <header className="flex items-start gap-3 border-b px-5 py-4">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-muted/50 text-muted-foreground">
                    <Icon className="size-4" />
                </div>
                <div className="space-y-0.5">
                    <h3 className="text-sm font-semibold leading-none">{title}</h3>
                    <p className="text-xs text-muted-foreground">{description}</p>
                </div>
            </header>
            <div className="p-5">{children}</div>
        </section>
    )
}

interface ToggleRowProps {
    id: string
    label: string
    description: string
    checked: boolean
    disabled?: boolean
    badge?: ReactNode
    onChange: (checked: boolean) => void
}

function ToggleRow({ id, label, description, checked, disabled, badge, onChange }: ToggleRowProps) {
    return (
        <div className="flex items-center justify-between gap-4 px-4 py-3.5">
            <div className="space-y-1">
                <div className="flex items-center gap-2">
                    <Label htmlFor={id} className="cursor-pointer text-sm font-medium">
                        {label}
                    </Label>
                    {badge}
                </div>
                <p className="text-sm leading-relaxed text-muted-foreground">{description}</p>
            </div>
            <Switch id={id} checked={checked} disabled={disabled} onCheckedChange={onChange} />
        </div>
    )
}

function RowSkeleton() {
    return (
        <div className="flex items-center justify-between gap-4 px-4 py-3.5">
            <div className="space-y-2">
                <Skeleton className="h-4 w-40" />
                <Skeleton className="h-3 w-64" />
            </div>
            <Skeleton className="h-5 w-8 rounded-full" />
        </div>
    )
}

export default function SystemSettingsPage() {
    const { data: settings, isLoading } = useSettings()
    const { data: ssoProviders } = useSsoProviders()
    const updateSettings = useUpdateSettings()

    const hasSso = (ssoProviders?.length ?? 0) > 0
    const autoUpdate = settings?.auto_update_currencies ?? true
    const passwordLoginEnabled = settings?.password_login_enabled ?? true
    const passwordLoginLocked = passwordLoginEnabled && !hasSso
    const ssoAllowSignup = settings?.sso_allow_signup ?? true
    const requireVerifiedEmail = settings?.sso_require_verified_email ?? false

    const handleAutoUpdateChange = (checked: boolean) => {
        updateSettings.mutate({ auto_update_currencies: checked })
    }

    const handlePasswordLoginChange = (checked: boolean) => {
        if (!checked && !hasSso) {
            toast.error('Enable at least one SSO provider before turning off password sign-in.')
            return
        }
        updateSettings.mutate({ password_login_enabled: checked })
    }

    const handleSignupChange = (checked: boolean) => {
        updateSettings.mutate({ sso_allow_signup: checked })
    }

    const handleRequireVerifiedChange = (checked: boolean) => {
        updateSettings.mutate({ sso_require_verified_email: checked })
    }

    return (
        <Page title="System Settings">
            <PageHeader title="System" description="Configure system settings" />

            <FormWrapper>
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <Section
                        icon={Coins}
                        title="Currency Rates"
                        description="Configure how currency exchange rates are managed"
                    >
                        <div className="rounded-lg border">
                            {isLoading ? (
                                <RowSkeleton />
                            ) : (
                                <ToggleRow
                                    id="auto-update"
                                    label="Auto-update currency rates"
                                    description="When enabled, rates are pulled daily from an external API and manual rate editing is disabled."
                                    checked={autoUpdate}
                                    disabled={updateSettings.isPending}
                                    onChange={handleAutoUpdateChange}
                                />
                            )}
                        </div>
                    </Section>

                    <Section
                        icon={ShieldCheck}
                        title="Authentication"
                        description="Control how users sign in to Savvy"
                    >
                        <div className="divide-y rounded-lg border">
                            {isLoading ? (
                                <>
                                    <RowSkeleton />
                                    <RowSkeleton />
                                    <RowSkeleton />
                                </>
                            ) : (
                                <>
                                    <ToggleRow
                                        id="password-login"
                                        label="Password sign-in"
                                        description={
                                            passwordLoginLocked
                                                ? 'Add and enable at least one SSO provider before you can turn this off — it keeps the instance from locking everyone out.'
                                                : 'When off, users can only sign in through SSO. Active sessions stay signed in.'
                                        }
                                        checked={passwordLoginEnabled}
                                        disabled={updateSettings.isPending || passwordLoginLocked}
                                        badge={
                                            passwordLoginLocked ? (
                                                <span className="inline-flex items-center gap-1 rounded-full border bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                                    <Lock className="size-2.5" />
                                                    Locked
                                                </span>
                                            ) : undefined
                                        }
                                        onChange={handlePasswordLoginChange}
                                    />

                                    <ToggleRow
                                        id="sso-signup"
                                        label="Just-in-time provisioning"
                                        description="Automatically create an account on first SSO sign-in. When off, only existing or linked users can sign in."
                                        checked={ssoAllowSignup}
                                        disabled={updateSettings.isPending}
                                        onChange={handleSignupChange}
                                    />

                                    <ToggleRow
                                        id="sso-verified-email"
                                        label="Require a verified email"
                                        description={
                                            ssoAllowSignup
                                                ? 'New SSO sign-ups must arrive with an email the provider has verified.'
                                                : 'Turn on just-in-time provisioning to enforce this.'
                                        }
                                        checked={requireVerifiedEmail}
                                        disabled={updateSettings.isPending || !ssoAllowSignup}
                                        onChange={handleRequireVerifiedChange}
                                    />
                                </>
                            )}
                        </div>
                    </Section>
                </div>
            </FormWrapper>
        </Page>
    )
}
