import { createContext, useContext, useMemo } from 'react'
import { createContextualCan } from '@casl/react'
import { defineAbilityFor, AppAbility } from '@/lib/ability'
import { useUser } from '@/stores/auth'

const AbilityContext = createContext<AppAbility>(defineAbilityFor(null))

export const Can = createContextualCan(AbilityContext.Consumer)

export function AbilityProvider({ children }: { children: React.ReactNode }) {
    const user = useUser()
    const ability = useMemo(() => defineAbilityFor(user?.role ?? null), [user?.role])

    return (
        <AbilityContext.Provider value={ability}>
            {children}
        </AbilityContext.Provider>
    )
}

export const useAbility = () => useContext(AbilityContext)
