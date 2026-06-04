import { useQuery } from '@tanstack/react-query'
import { monitoringApi } from '@/api/monitoring'

export function useStorageMonitoring() {
    return useQuery({
        queryKey: ['monitoring', 'storage'],
        queryFn: monitoringApi.storage,
        refetchInterval: 15_000,
        refetchOnWindowFocus: true,
    })
}

export function useResourceMonitoring() {
    return useQuery({
        queryKey: ['monitoring', 'resources'],
        queryFn: monitoringApi.resources,
        refetchInterval: 5_000,
        refetchOnWindowFocus: true,
    })
}
