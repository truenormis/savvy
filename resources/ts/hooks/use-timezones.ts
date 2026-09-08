import { useQuery } from '@tanstack/react-query'
import { timezonesApi } from '@/api'

export function useTimezones() {
    return useQuery({
        queryKey: ['timezones'],
        queryFn: timezonesApi.get,
        staleTime: Infinity,
    })
}
