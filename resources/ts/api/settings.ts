import { api } from './client'
import { Settings } from '@/types'

const ENDPOINT = '/settings'

export const settingsApi = {
    get: () =>
        api.get<Settings>(ENDPOINT),

    update: (data: Partial<Settings>) =>
        api.patch<Settings, Partial<Settings>>(ENDPOINT, data),
}
