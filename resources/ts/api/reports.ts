import { apiClient } from './client'
import { ReportConfig, ReportResult, ReportOptions } from '@/types'

const ENDPOINT = '/reports'

export const reportsApi = {
    getOptions: () =>
        apiClient.get<ReportOptions>(`${ENDPOINT}/options`).then(res => res.data),

    generate: (config: ReportConfig) =>
        apiClient.post<ReportResult>(`${ENDPOINT}/generate`, config).then(res => res.data),
}
