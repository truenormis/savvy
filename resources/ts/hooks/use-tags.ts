import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { tagsApi } from '@/api'
import { TagFormData } from '@/types'
import { toast } from 'sonner'

const QUERY_KEY = ['tags']

export function useTags() {
    return useQuery({
        queryKey: QUERY_KEY,
        queryFn: () => tagsApi.getAll(),
    })
}

export function useTag(id: string | number) {
    return useQuery({
        queryKey: [...QUERY_KEY, id],
        queryFn: () => tagsApi.getById(id),
        enabled: !!id,
    })
}

export function useCreateTag(redirectTo?: string) {
    const queryClient = useQueryClient()
    const navigate = useNavigate()

    return useMutation({
        mutationFn: (data: TagFormData) => tagsApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Tag created')
            if (redirectTo) navigate(redirectTo)
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to create tag')
        },
    })
}

export function useUpdateTag(redirectTo?: string) {
    const queryClient = useQueryClient()
    const navigate = useNavigate()

    return useMutation({
        mutationFn: ({ id, data }: { id: string | number; data: Partial<TagFormData> }) =>
            tagsApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Tag updated')
            if (redirectTo) navigate(redirectTo)
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to update tag')
        },
    })
}

export function useDeleteTag() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (id: string | number) => tagsApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Tag deleted')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to delete tag')
        },
    })
}
