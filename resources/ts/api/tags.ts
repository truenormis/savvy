import { api } from './client'
import { Tag, TagFormData } from '@/types'

const ENDPOINT = '/tags'

export const tagsApi = {
    getAll: () =>
        api.get<Tag[]>(ENDPOINT),

    getById: (id: number | string) =>
        api.get<Tag>(`${ENDPOINT}/${id}`),

    create: (data: TagFormData) =>
        api.post<Tag, TagFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<TagFormData>) =>
        api.patch<Tag, Partial<TagFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) =>
        api.delete<void>(`${ENDPOINT}/${id}`),
}
