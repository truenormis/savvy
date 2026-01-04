import { useEffect, useState } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/api'
import { Loader2 } from 'lucide-react'

export function AuthProvider() {
    const { isLoading, isAuthenticated, checkAuth } = useAuthStore()
    const location = useLocation()
    const [needsRegistration, setNeedsRegistration] = useState<boolean | null>(null)
    const [statusChecked, setStatusChecked] = useState(false)

    useEffect(() => {
        const checkStatus = async () => {
            try {
                const status = await authApi.status()
                setNeedsRegistration(status.needs_registration)
            } catch {
                setNeedsRegistration(false)
            } finally {
                setStatusChecked(true)
            }
        }
        checkStatus()
    }, [])

    useEffect(() => {
        if (statusChecked && !needsRegistration) {
            checkAuth()
        }
    }, [statusChecked, needsRegistration, checkAuth])

    // Still checking status
    if (!statusChecked) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <Loader2 className="size-8 animate-spin text-muted-foreground" />
            </div>
        )
    }

    // Needs setup - redirect to setup wizard
    if (needsRegistration) {
        return <Navigate to="/setup" state={{ from: location }} replace />
    }

    // Loading auth state
    if (isLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <Loader2 className="size-8 animate-spin text-muted-foreground" />
            </div>
        )
    }

    // Not authenticated - redirect to login
    if (!isAuthenticated) {
        return <Navigate to="/login" state={{ from: location }} replace />
    }

    return <Outlet />
}
