import { useUser } from '@/stores/auth'

export function useCanWrite() {
    const user = useUser()
    return user?.role !== 'read-only'
}
