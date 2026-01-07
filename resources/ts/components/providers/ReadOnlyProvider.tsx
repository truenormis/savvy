import { createContext, useContext } from 'react'
import { useUser } from '@/stores/auth'

const ReadOnlyContext = createContext(false)

export function ReadOnlyProvider({ children }: { children: React.ReactNode }) {
    const user = useUser()
    const isReadOnly = user?.role === 'read-only'

    return (
        <ReadOnlyContext.Provider value={isReadOnly}>
            {children}
        </ReadOnlyContext.Provider>
    )
}

export const useReadOnly = () => useContext(ReadOnlyContext)
