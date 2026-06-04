import { api } from './client'
import { StorageSnapshot, ResourceSnapshot } from '@/types/monitoring'

const ENDPOINT = '/monitoring'

export const monitoringApi = {
    storage: () =>
        api.get<StorageSnapshot>(`${ENDPOINT}/storage`),

    resources: () =>
        api.get<ResourceSnapshot>(`${ENDPOINT}/resources`),
}
