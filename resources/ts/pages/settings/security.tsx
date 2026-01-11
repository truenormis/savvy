import { useState } from 'react'
import { Page, PageHeader } from '@/components/shared'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
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
} from '@/hooks'
import { ShieldCheck, ShieldOff, Key, Copy, RefreshCw } from 'lucide-react'
import { toast } from 'sonner'
import { QRCode } from 'react-qrcode-logo'
import { useTheme } from '@/hooks'
import { useReadOnly } from '@/components/providers/ReadOnlyProvider'

type SetupStep = 'qr' | 'verify' | 'recovery'

export default function SecuritySettingsPage() {
    const { theme } = useTheme()
    const isReadOnly = useReadOnly()
    const { data: status, isLoading } = useTwoFactorStatus()
    const enableMutation = useEnableTwoFactor()
    const confirmMutation = useConfirmTwoFactor()
    const disableMutation = useDisableTwoFactor()
    const regenerateMutation = useRegenerateRecoveryCodes()

    const [showEnableDialog, setShowEnableDialog] = useState(false)
    const [showDisableDialog, setShowDisableDialog] = useState(false)
    const [showRegenerateDialog, setShowRegenerateDialog] = useState(false)
    const [setupStep, setSetupStep] = useState<SetupStep>('qr')
    const [qrData, setQrData] = useState<{ secret: string; qr_code_url: string } | null>(null)
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([])
    const [otpValue, setOtpValue] = useState('')

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
            <PageHeader
                title="Security"
                description="Manage your account security settings"
            />

            <div className="space-y-6">
                {/* 2FA Status Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {status?.enabled ? (
                                <ShieldCheck className="h-5 w-5 text-green-500" />
                            ) : (
                                <ShieldOff className="h-5 w-5 text-muted-foreground" />
                            )}
                            Two-Factor Authentication
                        </CardTitle>
                        <CardDescription>
                            Add an extra layer of security to your account by requiring a verification code in addition to your password.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <div className="flex items-center justify-between">
                                <Skeleton className="h-6 w-24" />
                                <Skeleton className="h-9 w-24" />
                            </div>
                        ) : (
                            <div className="flex items-center justify-between">
                                <Badge variant={status?.enabled ? 'default' : 'secondary'}>
                                    {status?.enabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                                {status?.enabled ? (
                                    <Button
                                        variant="destructive"
                                        onClick={() => setShowDisableDialog(true)}
                                        disabled={isReadOnly}
                                    >
                                        Disable 2FA
                                    </Button>
                                ) : (
                                    <Button onClick={handleEnable} disabled={enableMutation.isPending || isReadOnly}>
                                        {enableMutation.isPending ? 'Enabling...' : 'Enable 2FA'}
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Recovery Codes Card */}
                {status?.enabled && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Key className="h-5 w-5" />
                                Recovery Codes
                            </CardTitle>
                            <CardDescription>
                                Recovery codes can be used to access your account if you lose your authenticator device.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    {status.recovery_codes_remaining} codes remaining
                                </div>
                                <Button
                                    variant="outline"
                                    onClick={() => setShowRegenerateDialog(true)}
                                    disabled={isReadOnly}
                                >
                                    <RefreshCw className="h-4 w-4 mr-2" />
                                    Regenerate
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

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
        </Page>
    )
}
