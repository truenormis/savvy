import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/api/client'
import { toast } from 'sonner'
import { useNavigate } from 'react-router-dom'

interface UseCrudOptions<T> {
    endpoint: string
    queryKey: string[]
    redirectTo?: string
}

export function useCrud<T extends { id: number | string }>({
                                                               endpoint,
                                                               queryKey,
                                                               redirectTo
                                                           }: UseCrudOptions<T>) {
    const qc = useQueryClient()
    const navigate = useNavigate()

    const list = useQuery({
        queryKey,
        queryFn: () => api.get<T[]>(endpoint),
    })

    const create = useMutation({
        mutationFn: (data: Partial<T>) => api.post<T, Partial<T>>(endpoint, data),
        onSuccess: () => {
            toast.success('Created successfully')
            qc.invalidateQueries({ queryKey })
            if (redirectTo) navigate(redirectTo)
        },
    })

    const update = useMutation({
        mutationFn: ({ id, data }: { id: string | number; data: Partial<T> }) =>
            api.patch<T, Partial<T>>(`${endpoint}/${id}`, data),
        onSuccess: () => {
            toast.success('Updated successfully')
            qc.invalidateQueries({ queryKey })
            if (redirectTo) navigate(redirectTo)
        },
    })

    const remove = useMutation({
        mutationFn: (id: string | number) => api.delete<void>(`${endpoint}/${id}`),
        onSuccess: () => {
            toast.success('Deleted successfully')
            qc.invalidateQueries({ queryKey })
        },
    })

    return { list, create, update, remove }
}

// Separate hook for fetching single item (avoids conditional hook call)
export function useCrudItem<T>(endpoint: string, queryKey: string[], id: string | number) {
    return useQuery({
        queryKey: [...queryKey, id],
        queryFn: () => api.get<T>(`${endpoint}/${id}`),
        enabled: !!id,
    })
}
