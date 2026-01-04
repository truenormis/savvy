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
import { Loader2 } from 'lucide-react'
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
    const [isLoading, setIsLoading] = useState(false)
    const [checkingStatus, setCheckingStatus] = useState(true)

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
            await login(data)
            toast.success('Welcome back!')
            navigate('/')
        } catch (error: unknown) {
            const message = error && typeof error === 'object' && 'message' in error
                ? (error as { message: string }).message
                : 'Invalid credentials'
            toast.error(message)
        } finally {
            setIsLoading(false)
        }
    }

    if (checkingStatus) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-background">
                <Loader2 className="size-8 animate-spin text-muted-foreground" />
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
