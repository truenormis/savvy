import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '@/lib/query-client'
import { Toaster } from 'sonner'
import { AbilityProvider } from '@/components/providers/AbilityProvider'
import { ReadOnlyProvider } from '@/components/providers/ReadOnlyProvider'

export function Providers({ children }: { children: React.ReactNode }) {
    return (
        <QueryClientProvider client={queryClient}>
            <AbilityProvider>
                <ReadOnlyProvider>
                    {children}
                </ReadOnlyProvider>
            </AbilityProvider>
            <Toaster position="top-right" richColors />
        </QueryClientProvider>
    )
}
