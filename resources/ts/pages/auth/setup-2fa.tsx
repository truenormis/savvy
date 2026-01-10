import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
    InputOTPSeparator,
} from '@/components/ui/input-otp'
import { useEnableTwoFactor, useConfirmTwoFactor, useTheme } from '@/hooks'
import { ShieldCheck, Copy, Loader2, ArrowLeft, Star } from 'lucide-react'
import { toast } from 'sonner'
import { QRCode } from 'react-qrcode-logo'

type SetupStep = 'intro' | 'qr' | 'verify' | 'recovery' | 'star'

export default function Setup2FAPage() {
    const navigate = useNavigate()
    const { theme } = useTheme()

    const [step, setStep] = useState<SetupStep>('intro')
    const [qrData, setQrData] = useState<{ secret: string; qr_code_url: string } | null>(null)
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([])
    const [otpValue, setOtpValue] = useState('')

    const enableMutation = useEnableTwoFactor()
    const confirmMutation = useConfirmTwoFactor()

    useEffect(() => {
        if (sessionStorage.getItem('just_registered') !== 'true') {
            navigate('/', { replace: true })
        }
    }, [navigate])

    const handleSkip = () => {
        toast.info('You can enable 2FA later in Settings > Security')
        setStep('star')
    }

    const handleComplete = () => {
        toast.success('2FA enabled successfully!')
        setStep('star')
    }

    const handleFinish = () => {
        sessionStorage.removeItem('just_registered')
        navigate('/')
    }

    const handleEnable = async () => {
        const data = await enableMutation.mutateAsync()
        setQrData(data)
        setStep('qr')
    }

    const handleConfirm = async () => {
        const data = await confirmMutation.mutateAsync(otpValue)
        setRecoveryCodes(data.recovery_codes)
        setStep('recovery')
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

    return (
        <div className="min-h-screen flex items-center justify-center bg-background p-4">
            <Card className="w-full max-w-md">
                {step === 'intro' && (
                    <>
                        <CardHeader className="text-center">
                            <div className="flex justify-center mb-4">
                                <div className="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                    <ShieldCheck className="size-7" />
                                </div>
                            </div>
                            <CardTitle className="text-2xl">Secure Your Account</CardTitle>
                            <CardDescription>
                                Add two-factor authentication for extra security. This protects your account even if your password is compromised.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="text-sm text-muted-foreground space-y-2">
                                <p>With 2FA enabled:</p>
                                <ul className="list-disc list-inside space-y-1">
                                    <li>You'll need your phone to log in</li>
                                    <li>Your account is protected from unauthorized access</li>
                                    <li>You'll get recovery codes for emergencies</li>
                                </ul>
                            </div>
                            <div className="flex flex-col gap-2">
                                <Button
                                    onClick={handleEnable}
                                    disabled={enableMutation.isPending}
                                    className="w-full"
                                >
                                    {enableMutation.isPending && <Loader2 className="mr-2 size-4 animate-spin" />}
                                    Enable 2FA
                                </Button>
                                <Button
                                    variant="ghost"
                                    onClick={handleSkip}
                                    className="w-full"
                                >
                                    Skip for now
                                </Button>
                            </div>
                        </CardContent>
                    </>
                )}

                {step === 'qr' && qrData && (
                    <>
                        <CardHeader className="text-center">
                            <CardTitle>Scan QR Code</CardTitle>
                            <CardDescription>
                                Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex justify-center">
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
                            </div>
                            <div>
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
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setStep('intro')}
                                    className="flex-1"
                                >
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back
                                </Button>
                                <Button
                                    onClick={() => setStep('verify')}
                                    className="flex-1"
                                >
                                    Continue
                                </Button>
                            </div>
                        </CardContent>
                    </>
                )}

                {step === 'verify' && (
                    <>
                        <CardHeader className="text-center">
                            <CardTitle>Verify Setup</CardTitle>
                            <CardDescription>
                                Enter the 6-digit code from your authenticator app
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="flex justify-center">
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
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setStep('qr')}
                                    className="flex-1"
                                >
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back
                                </Button>
                                <Button
                                    onClick={handleConfirm}
                                    disabled={otpValue.length !== 6 || confirmMutation.isPending}
                                    className="flex-1"
                                >
                                    {confirmMutation.isPending && <Loader2 className="mr-2 size-4 animate-spin" />}
                                    Verify
                                </Button>
                            </div>
                        </CardContent>
                    </>
                )}

                {step === 'recovery' && recoveryCodes.length > 0 && (
                    <>
                        <CardHeader className="text-center">
                            <CardTitle>Save Your Recovery Codes</CardTitle>
                            <CardDescription>
                                Store these codes in a safe place. Each code can only be used once to access your account if you lose your device.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="bg-muted p-4 rounded-md font-mono text-sm grid grid-cols-2 gap-2">
                                {recoveryCodes.map((code, index) => (
                                    <div key={index} className="text-center">
                                        {code}
                                    </div>
                                ))}
                            </div>
                            <Button
                                variant="outline"
                                className="w-full"
                                onClick={copyRecoveryCodes}
                            >
                                <Copy className="h-4 w-4 mr-2" />
                                Copy All Codes
                            </Button>
                            <Button onClick={handleComplete} className="w-full">
                                Done
                            </Button>
                        </CardContent>
                    </>
                )}

                {step === 'star' && (
                    <>
                        <CardHeader className="text-center">
                            <div className="flex justify-center mb-4">
                                <div className="flex size-12 items-center justify-center rounded-xl bg-yellow-500 text-white">
                                    <Star className="size-7" />
                                </div>
                            </div>
                            <CardTitle className="text-2xl">Support Savvy</CardTitle>
                            <CardDescription>
                                If you enjoy using Savvy, please consider giving us a star on GitHub. It helps others discover the project!
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-col gap-2">
                                <Button
                                    asChild
                                    className="w-full"
                                >
                                    <a
                                        href="https://github.com/truenormis/savvy"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Star className="mr-2 size-4" />
                                        Star on GitHub
                                    </a>
                                </Button>
                                <Button
                                    variant="ghost"
                                    onClick={handleFinish}
                                    className="w-full"
                                >
                                    Skip
                                </Button>
                            </div>
                        </CardContent>
                    </>
                )}
            </Card>
        </div>
    )
}
