import { useQuery, useMutation } from '@tanstack/react-query'
import { reportsApi } from '@/api'
import { ReportConfig } from '@/types'

export function useReportOptions() {
    return useQuery({
        queryKey: ['reports', 'options'],
        queryFn: () => reportsApi.getOptions(),
        staleTime: 1000 * 60 * 60, // 1 hour
    })
}

export function useGenerateReport() {
    return useMutation({
        mutationFn: (config: ReportConfig) => reportsApi.generate(config),
    })
}
