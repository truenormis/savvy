import { useUser } from '@/stores/auth'

export function useIsAdmin() {
    const user = useUser()

    return user?.role === 'admin'
}
