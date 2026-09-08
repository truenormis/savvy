export interface Timezone {
    name: string
    offset: string
    canonical: boolean
}

export interface TimezoneOptions {
    timezones: Timezone[]
    current: string
}
