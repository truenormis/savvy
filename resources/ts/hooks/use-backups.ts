import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { backupsApi } from '@/api/backups'
import { toast } from 'sonner'

const QUERY_KEY = ['backups']

export function useBackups() {
    return useQuery({
        queryKey: QUERY_KEY,
        queryFn: backupsApi.getAll,
    })
}

export function useCreateBackup() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (note?: string) => backupsApi.create(note),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Backup created')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to create backup')
        },
    })
}

export function useUploadBackup() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: ({ file, note }: { file: File; note?: string }) =>
            backupsApi.upload(file, note),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Backup uploaded')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to upload backup')
        },
    })
}

export function useRestoreBackup() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (id: number) => backupsApi.restore(id),
        onSuccess: () => {
            queryClient.invalidateQueries()
            toast.success('Database restored successfully')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to restore backup')
        },
    })
}

export function useDeleteBackup() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (id: number) => backupsApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Backup deleted')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to delete backup')
        },
    })
}
