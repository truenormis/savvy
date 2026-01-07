import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form'
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
    InputOTPSeparator,
} from '@/components/ui/input-otp'
import { Loader2, ArrowLeft, Key } from 'lucide-react'
import { useAuthStore } from '@/stores/auth'
import { Logo } from '@/components/shared/Logo'
import { authApi } from '@/api'
import { toast } from 'sonner'

const loginSchema = z.object({
    email: z.string().email('Invalid email'),
    password: z.string().min(1, 'Password is required'),
})

type LoginFormValues = z.infer<typeof loginSchema>

export default function LoginPage() {
    const navigate = useNavigate()
    const login = useAuthStore((state) => state.login)
    const loginWith2FA = useAuthStore((state) => state.loginWith2FA)
    const [isLoading, setIsLoading] = useState(false)
    const [checkingStatus, setCheckingStatus] = useState(true)
    const [twoFactorToken, setTwoFactorToken] = useState<string | null>(null)
    const [otpValue, setOtpValue] = useState('')
    const [useRecoveryCode, setUseRecoveryCode] = useState(false)
    const [recoveryCode, setRecoveryCode] = useState('')

    useEffect(() => {
        authApi.status().then((status) => {
            if (status.needs_registration) {
                navigate('/setup', { replace: true })
            }
        }).finally(() => setCheckingStatus(false))
    }, [navigate])

    const form = useForm<LoginFormValues>({
        resolver: zodResolver(loginSchema),
        defaultValues: {
            email: '',
            password: '',
        },
    })

    const onSubmit = async (data: LoginFormValues) => {
        setIsLoading(true)
        try {
            const result = await login(data)
            if (result.success) {
                toast.success('Welcome back!')
                navigate('/')
            } else if (result.requires_2fa) {
                setTwoFactorToken(result.two_factor_token)
            }
        } catch (error: unknown) {
            const message = error && typeof error === 'object' && 'message' in error
                ? (error as { message: string }).message
                : 'Invalid credentials'
            toast.error(message)
        } finally {
            setIsLoading(false)
        }
    }

    const onVerify2FA = async () => {
        const code = useRecoveryCode ? recoveryCode : otpValue
        if (!twoFactorToken || (!useRecoveryCode && otpValue.length !== 6) || (useRecoveryCode && !recoveryCode)) return

        setIsLoading(true)
        try {
            await loginWith2FA(twoFactorToken, code)
            toast.success('Welcome back!')
            navigate('/')
        } catch (error: unknown) {
            const message = error && typeof error === 'object' && 'message' in error
                ? (error as { message: string }).message
                : 'Invalid verification code'
            toast.error(message)
            if (useRecoveryCode) {
                setRecoveryCode('')
            } else {
                setOtpValue('')
            }
        } finally {
            setIsLoading(false)
        }
    }

    const handleBack = () => {
        setTwoFactorToken(null)
        setOtpValue('')
        setRecoveryCode('')
        setUseRecoveryCode(false)
    }

    if (checkingStatus) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-background">
                <Loader2 className="size-8 animate-spin text-muted-foreground" />
            </div>
        )
    }

    // 2FA Verification Step
    if (twoFactorToken) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-background p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="flex justify-center mb-4">
                            <div className="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <Logo className="size-7" />
                            </div>
                        </div>
                        <CardTitle className="text-2xl">Two-Factor Authentication</CardTitle>
                        <CardDescription>
                            {useRecoveryCode
                                ? 'Enter one of your recovery codes'
                                : 'Enter the 6-digit code from your authenticator app'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {useRecoveryCode ? (
                            <Input
                                type="text"
                                placeholder="xxxx-xxxx"
                                value={recoveryCode}
                                onChange={(e) => setRecoveryCode(e.target.value)}
                                className="text-center font-mono text-lg"
                                autoFocus
                            />
                        ) : (
                            <div className="flex justify-center">
                                <InputOTP
                                    maxLength={6}
                                    value={otpValue}
                                    onChange={setOtpValue}
                                    onComplete={onVerify2FA}
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
                        )}

                        <div className="flex flex-col gap-2">
                            <Button
                                onClick={onVerify2FA}
                                className="w-full"
                                disabled={(!useRecoveryCode && otpValue.length !== 6) || (useRecoveryCode && !recoveryCode) || isLoading}
                            >
                                {isLoading && <Loader2 className="mr-2 size-4 animate-spin" />}
                                Verify
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    setUseRecoveryCode(!useRecoveryCode)
                                    setOtpValue('')
                                    setRecoveryCode('')
                                }}
                                className="w-full"
                            >
                                <Key className="mr-2 size-4" />
                                {useRecoveryCode ? 'Use authenticator code' : 'Use recovery code'}
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={handleBack}
                                className="w-full"
                            >
                                <ArrowLeft className="mr-2 size-4" />
                                Back to login
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        )
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-background p-4">
            <Card className="w-full max-w-md">
                <CardHeader className="text-center">
                    <div className="flex justify-center mb-4">
                        <div className="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <Logo className="size-7" />
                        </div>
                    </div>
                    <CardTitle className="text-2xl">Welcome back</CardTitle>
                    <CardDescription>
                        Sign in to your account to continue
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form {...form}>
                        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
                            <FormField
                                control={form.control}
                                name="email"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Email</FormLabel>
                                        <FormControl>
                                            <Input
                                                type="email"
                                                placeholder="you@example.com"
                                                autoComplete="email"
                                                {...field}
                                            />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />

                            <FormField
                                control={form.control}
                                name="password"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Password</FormLabel>
                                        <FormControl>
                                            <Input
                                                type="password"
                                                placeholder="Enter your password"
                                                autoComplete="current-password"
                                                {...field}
                                            />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />

                            <Button type="submit" className="w-full" disabled={isLoading}>
                                {isLoading && <Loader2 className="mr-2 size-4 animate-spin" />}
                                Sign in
                            </Button>
                        </form>
                    </Form>
                </CardContent>
            </Card>
        </div>
    )
}
