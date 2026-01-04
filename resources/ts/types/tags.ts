import { BaseEntity } from './api'

export interface Tag extends BaseEntity {
    name: string
    transactionsCount?: number
}

export interface TagFormData {
    name: string
}
