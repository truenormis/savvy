import { api } from './client'
import { TimezoneOptions } from '@/types'

const ENDPOINT = '/timezones'

export const timezonesApi = {
    get: () =>
        api.get<TimezoneOptions>(ENDPOINT),
}
