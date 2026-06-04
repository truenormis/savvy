import { ReactNode, useState } from 'react'
import type { LucideIcon } from 'lucide-react'
import { Page, PageHeader, FormWrapper } from '@/components/shared'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Skeleton } from '@/components/ui/skeleton'
import { Input } from '@/components/ui/input'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
    InputOTPSeparator,
} from '@/components/ui/input-otp'
import {
    useTwoFactorStatus,
    useEnableTwoFactor,
    useConfirmTwoFactor,
    useDisableTwoFactor,
    useRegenerateRecoveryCodes,
    useWebauthnCredentials,
    useRegisterPasskey,
    useDeletePasskey,
    isPasskeyDomainSupported,
} from '@/hooks'
import { browserSupportsWebAuthn } from '@simplewebauthn/browser'
import { ShieldCheck, Key, Copy, RefreshCw, Fingerprint, Trash2, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { QRCode } from 'react-qrcode-logo'
import { useTheme } from '@/hooks'
import { useReadOnly } from '@/components/providers/ReadOnlyProvider'

type SetupStep = 'qr' | 'verify' | 'recovery'

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

function ActionRow({
    label,
    description,
    badge,
    children,
}: {
    label: string
    description: ReactNode
    badge?: ReactNode
    children: ReactNode
}) {
    return (
        <div className="flex items-center justify-between gap-4 px-4 py-3.5">
            <div className="min-w-0 space-y-1">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{label}</span>
                    {badge}
                </div>
                <p className="text-sm leading-relaxed text-muted-foreground">{description}</p>
            </div>
            {children}
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
            <Skeleton className="h-9 w-24" />
        </div>
    )
}

export default function SecuritySettingsPage() {
    const { theme } = useTheme()
    const isReadOnly = useReadOnly()
    const { data: status, isLoading } = useTwoFactorStatus()
    const enableMutation = useEnableTwoFactor()
    const confirmMutation = useConfirmTwoFactor()
    const disableMutation = useDisableTwoFactor()
    const regenerateMutation = useRegenerateRecoveryCodes()

    const passkeySupported = browserSupportsWebAuthn()
    const passkeyDomainValid = isPasskeyDomainSupported()
    const { data: passkeys, isLoading: passkeysLoading } = useWebauthnCredentials()
    const registerPasskey = useRegisterPasskey()
    const deletePasskey = useDeletePasskey()

    const [showEnableDialog, setShowEnableDialog] = useState(false)
    const [showDisableDialog, setShowDisableDialog] = useState(false)
    const [showRegenerateDialog, setShowRegenerateDialog] = useState(false)
    const [setupStep, setSetupStep] = useState<SetupStep>('qr')
    const [qrData, setQrData] = useState<{ secret: string; qr_code_url: string } | null>(null)
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([])
    const [otpValue, setOtpValue] = useState('')
    const [showAddPasskeyDialog, setShowAddPasskeyDialog] = useState(false)
    const [passkeyName, setPasskeyName] = useState('')

    const handleEnable = async () => {
        const data = await enableMutation.mutateAsync()
        setQrData(data)
        setSetupStep('qr')
        setShowEnableDialog(true)
    }

    const handleConfirm = async () => {
        const data = await confirmMutation.mutateAsync(otpValue)
        setRecoveryCodes(data.recovery_codes)
        setSetupStep('recovery')
        setOtpValue('')
    }

    const handleDisable = async () => {
        await disableMutation.mutateAsync(otpValue)
        setShowDisableDialog(false)
        setOtpValue('')
    }

    const handleRegenerate = async () => {
        const data = await regenerateMutation.mutateAsync(otpValue)
        setRecoveryCodes(data.recovery_codes)
        setShowRegenerateDialog(false)
        setSetupStep('recovery')
        setShowEnableDialog(true)
        setOtpValue('')
    }

    const handleAddPasskey = async () => {
        await registerPasskey.mutateAsync(passkeyName.trim() || null)
        setShowAddPasskeyDialog(false)
        setPasskeyName('')
    }

    const copySecret = () => {
        if (qrData?.secret) {
            navigator.clipboard.writeText(qrData.secret)
            toast.success('Secret copied to clipboard')
        }
    }

    const copyRecoveryCodes = () => {
        navigator.clipboard.writeText(recoveryCodes.join('\n'))
        toast.success('Recovery codes copied to clipboard')
    }

    const closeEnableDialog = () => {
        setShowEnableDialog(false)
        setSetupStep('qr')
        setQrData(null)
        setRecoveryCodes([])
        setOtpValue('')
    }

    return (
        <Page title="Security Settings">
            <PageHeader title="Security" description="Manage your account security settings" />

            <FormWrapper>
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    {/* Two-Factor Authentication */}
                    <Section
                        icon={ShieldCheck}
                        title="Two-Factor Authentication"
                        description="Require a verification code in addition to your password"
                    >
                        <div className="rounded-lg border">
                            {isLoading ? (
                                <RowSkeleton />
                            ) : (
                                <ActionRow
                                    label="Authenticator app"
                                    description="Use a TOTP app such as Google Authenticator or Authy to generate sign-in codes."
                                >
                                    <Switch
                                        checked={status?.enabled ?? false}
                                        disabled={isReadOnly || enableMutation.isPending}
                                        onCheckedChange={(checked) =>
                                            checked ? handleEnable() : setShowDisableDialog(true)
                                        }
                                        aria-label="Toggle two-factor authentication"
                                    />
                                </ActionRow>
                            )}
                        </div>
                    </Section>

                    {/* Passkeys */}
                    {passkeySupported && (
                        <Section
                            icon={Fingerprint}
                            title="Passkeys"
                            description="Sign in without a password using biometrics, a screen lock, or a security key"
                        >
                            <div className="space-y-4">
                                {passkeysLoading ? (
                                    <div className="rounded-lg border">
                                        <RowSkeleton />
                                    </div>
                                ) : passkeys && passkeys.length > 0 ? (
                                    <div className="divide-y rounded-lg border">
                                        {passkeys.map((passkey) => (
                                            <div key={passkey.id} className="flex items-center justify-between gap-4 px-4 py-3.5">
                                                <div className="min-w-0 space-y-1">
                                                    <div className="truncate text-sm font-medium">
                                                        {passkey.name || 'Unnamed passkey'}
                                                    </div>
                                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                                        Added {new Date(passkey.created_at).toLocaleDateString()}
                                                        {passkey.last_used_at
                                                            ? ` · Last used ${new Date(passkey.last_used_at).toLocaleDateString()}`
                                                            : ' · Never used'}
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => deletePasskey.mutate(passkey.id)}
                                                    disabled={isReadOnly || deletePasskey.isPending}
                                                    aria-label="Remove passkey"
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                                        No passkeys registered yet.
                                    </div>
                                )}
                                {!passkeyDomainValid && (
                                    <p className="text-sm text-muted-foreground">
                                        Passkeys require a secure origin and a valid domain. Open the app over HTTPS or via{' '}
                                        <code className="rounded bg-muted px-1 py-0.5 text-xs">localhost</code> — raw IP addresses are not allowed.
                                    </p>
                                )}
                                <Button
                                    onClick={() => setShowAddPasskeyDialog(true)}
                                    disabled={isReadOnly || registerPasskey.isPending || !passkeyDomainValid}
                                >
                                    <Plus className="mr-2 size-4" />
                                    Add passkey
                                </Button>
                            </div>
                        </Section>
                    )}

                    {/* Recovery Codes */}
                    {status?.enabled && (
                        <Section
                            icon={Key}
                            title="Recovery Codes"
                            description="Single-use codes to access your account if you lose your authenticator"
                        >
                            <div className="rounded-lg border">
                                <ActionRow
                                    label="Backup recovery codes"
                                    description={`${status.recovery_codes_remaining} codes remaining. Regenerating invalidates the old set.`}
                                >
                                    <Button
                                        variant="outline"
                                        onClick={() => setShowRegenerateDialog(true)}
                                        disabled={isReadOnly}
                                    >
                                        <RefreshCw className="mr-2 size-4" />
                                        Regenerate
                                    </Button>
                                </ActionRow>
                            </div>
                        </Section>
                    )}
                </div>
            </FormWrapper>

            {/* Enable 2FA Dialog */}
            <Dialog open={showEnableDialog} onOpenChange={closeEnableDialog}>
                <DialogContent className="sm:max-w-md">
                    {setupStep === 'qr' && qrData && (
                        <>
                            <DialogHeader>
                                <DialogTitle>Set up Two-Factor Authentication</DialogTitle>
                                <DialogDescription>
                                    Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex flex-col items-center gap-4 py-4">
                                <QRCode
                                    value={qrData.qr_code_url}
                                    size={240}
                                    ecLevel="M"
                                    bgColor="transparent"
                                    fgColor={theme === 'dark' ? '#ffffff' : '#0f172a'}
                                    qrStyle="dots"
                                    eyeRadius={6}
                                    quietZone={0}
                                />
                                <div className="w-full">
                                    <p className="text-sm text-muted-foreground mb-2 text-center">
                                        Or enter this code manually:
                                    </p>
                                    <div className="flex items-center gap-2 bg-muted p-2 rounded-md">
                                        <code className="flex-1 text-sm font-mono break-all">
                                            {qrData.secret}
                                        </code>
                                        <Button variant="ghost" size="sm" onClick={copySecret}>
                                            <Copy className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button onClick={() => setSetupStep('verify')}>
                                    Continue
                                </Button>
                            </DialogFooter>
                        </>
                    )}

                    {setupStep === 'verify' && (
                        <>
                            <DialogHeader>
                                <DialogTitle>Verify Setup</DialogTitle>
                                <DialogDescription>
                                    Enter the 6-digit code from your authenticator app to confirm setup.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex justify-center py-6">
                                <InputOTP
                                    maxLength={6}
                                    value={otpValue}
                                    onChange={setOtpValue}
                                >
                                    <InputOTPGroup>
                                        <InputOTPSlot index={0} />
                                        <InputOTPSlot index={1} />
                                        <InputOTPSlot index={2} />
                                    </InputOTPGroup>
                                    <InputOTPSeparator />
                                    <InputOTPGroup>
                                        <InputOTPSlot index={3} />
                                        <InputOTPSlot index={4} />
                                        <InputOTPSlot index={5} />
                                    </InputOTPGroup>
                                </InputOTP>
                            </div>
                            <DialogFooter className="gap-2 sm:gap-0">
                                <Button variant="outline" onClick={() => setSetupStep('qr')}>
                                    Back
                                </Button>
                                <Button
                                    onClick={handleConfirm}
                                    disabled={otpValue.length !== 6 || confirmMutation.isPending}
                                >
                                    {confirmMutation.isPending ? 'Verifying...' : 'Verify'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}

                    {setupStep === 'recovery' && recoveryCodes.length > 0 && (
                        <>
                            <DialogHeader>
                                <DialogTitle>Save Your Recovery Codes</DialogTitle>
                                <DialogDescription>
                                    Store these codes in a safe place. Each code can only be used once.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="py-4">
                                <div className="bg-muted p-4 rounded-md font-mono text-sm grid grid-cols-2 gap-2">
                                    {recoveryCodes.map((code, index) => (
                                        <div key={index} className="text-center">
                                            {code}
                                        </div>
                                    ))}
                                </div>
                                <Button
                                    variant="outline"
                                    className="w-full mt-4"
                                    onClick={copyRecoveryCodes}
                                >
                                    <Copy className="h-4 w-4 mr-2" />
                                    Copy All Codes
                                </Button>
                            </div>
                            <DialogFooter>
                                <Button onClick={closeEnableDialog}>
                                    Done
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {/* Disable 2FA Dialog */}
            <Dialog open={showDisableDialog} onOpenChange={setShowDisableDialog}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Disable Two-Factor Authentication</DialogTitle>
                        <DialogDescription>
                            Enter a verification code to disable 2FA. You can use a code from your authenticator app or a recovery code.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex justify-center py-6">
                        <InputOTP
                            maxLength={6}
                            value={otpValue}
                            onChange={setOtpValue}
                        >
                            <InputOTPGroup>
                                <InputOTPSlot index={0} />
                                <InputOTPSlot index={1} />
                                <InputOTPSlot index={2} />
                            </InputOTPGroup>
                            <InputOTPSeparator />
                            <InputOTPGroup>
                                <InputOTPSlot index={3} />
                                <InputOTPSlot index={4} />
                                <InputOTPSlot index={5} />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button variant="outline" onClick={() => {
                            setShowDisableDialog(false)
                            setOtpValue('')
                        }}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDisable}
                            disabled={otpValue.length !== 6 || disableMutation.isPending}
                        >
                            {disableMutation.isPending ? 'Disabling...' : 'Disable 2FA'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Regenerate Recovery Codes Dialog */}
            <Dialog open={showRegenerateDialog} onOpenChange={setShowRegenerateDialog}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Regenerate Recovery Codes</DialogTitle>
                        <DialogDescription>
                            This will invalidate all existing recovery codes. Enter a verification code from your authenticator app to continue.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex justify-center py-6">
                        <InputOTP
                            maxLength={6}
                            value={otpValue}
                            onChange={setOtpValue}
                        >
                            <InputOTPGroup>
                                <InputOTPSlot index={0} />
                                <InputOTPSlot index={1} />
                                <InputOTPSlot index={2} />
                            </InputOTPGroup>
                            <InputOTPSeparator />
                            <InputOTPGroup>
                                <InputOTPSlot index={3} />
                                <InputOTPSlot index={4} />
                                <InputOTPSlot index={5} />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button variant="outline" onClick={() => {
                            setShowRegenerateDialog(false)
                            setOtpValue('')
                        }}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleRegenerate}
                            disabled={otpValue.length !== 6 || regenerateMutation.isPending}
                        >
                            {regenerateMutation.isPending ? 'Regenerating...' : 'Regenerate'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Add Passkey Dialog */}
            <Dialog open={showAddPasskeyDialog} onOpenChange={(open) => {
                setShowAddPasskeyDialog(open)
                if (!open) setPasskeyName('')
            }}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add a passkey</DialogTitle>
                        <DialogDescription>
                            Give this passkey a name so you can recognize it later, then follow your browser's prompt.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-2">
                        <Input
                            placeholder="e.g. MacBook Touch ID, iPhone, YubiKey"
                            value={passkeyName}
                            onChange={(e) => setPasskeyName(e.target.value)}
                            maxLength={100}
                            autoFocus
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !registerPasskey.isPending) handleAddPasskey()
                            }}
                        />
                    </div>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button variant="outline" onClick={() => {
                            setShowAddPasskeyDialog(false)
                            setPasskeyName('')
                        }}>
                            Cancel
                        </Button>
                        <Button onClick={handleAddPasskey} disabled={registerPasskey.isPending}>
                            {registerPasskey.isPending ? 'Waiting for device...' : 'Continue'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Page>
    )
}
