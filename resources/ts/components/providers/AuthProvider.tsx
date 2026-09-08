import { useEffect } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuthStore, useAuthLoading, useIsAuthenticated } from '@/stores/auth'
import { authApi } from '@/api'
import { Loader2 } from 'lucide-react'

function FullScreenLoader() {
    return (
        <div className="min-h-screen flex items-center justify-center">
            <Loader2 className="size-8 animate-spin text-muted-foreground" />
        </div>
    )
}

export function AuthProvider() {
    const location = useLocation()
    const isLoading = useAuthLoading()
    const isAuthenticated = useIsAuthenticated()
    const checkAuth = useAuthStore((state) => state.checkAuth)

    // Cached + request-deduped: survives StrictMode double-mount and child
    // navigations without re-hitting the network or flashing the spinner.
    const { data: status, isPending: statusPending } = useQuery({
        queryKey: ['auth', 'status'],
        queryFn: authApi.status,
        staleTime: Infinity,
        retry: false,
    })

    const needsRegistration = status?.needs_registration ?? false

    useEffect(() => {
        if (!statusPending && !needsRegistration) {
            checkAuth()
        }
    }, [statusPending, needsRegistration, checkAuth])

    if (statusPending) {
        return <FullScreenLoader />
    }

    if (needsRegistration) {
        return <Navigate to="/setup" state={{ from: location }} replace />
    }

    if (isLoading) {
        return <FullScreenLoader />
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" state={{ from: location }} replace />
    }

    return <Outlet />
}
