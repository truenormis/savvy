import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { settingsApi } from '@/api'
import { Settings } from '@/types'
import { toast } from 'sonner'

const QUERY_KEY = ['settings']

export function useSettings() {
    return useQuery({
        queryKey: QUERY_KEY,
        queryFn: settingsApi.get,
    })
}

export function useUpdateSettings() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (data: Partial<Settings>) => settingsApi.update(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Settings updated')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to update settings')
        },
    })
}
