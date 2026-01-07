import { useMutation, useQueryClient } from '@tanstack/react-query'
import { importApi } from '@/api/import'
import { toast } from 'sonner'
import type { ColumnMapping, ImportOptions } from '@/types/import'

export function useParseCSV() {
    return useMutation({
        mutationFn: (file: File) => importApi.parse(file),
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to parse CSV file')
        },
    })
}

export function usePreviewImport() {
    return useMutation({
        mutationFn: ({
            importId,
            mapping,
            options,
        }: {
            importId: string
            mapping: ColumnMapping
            options: ImportOptions
        }) => importApi.preview(importId, mapping, options),
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to preview import')
        },
    })
}

export function useExecuteImport() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: ({
            importId,
            mapping,
            options,
        }: {
            importId: string
            mapping: ColumnMapping
            options: ImportOptions
        }) => importApi.import(importId, mapping, options),
        onSuccess: (data) => {
            // Invalidate related queries
            queryClient.invalidateQueries({ queryKey: ['transactions'] })
            queryClient.invalidateQueries({ queryKey: ['accounts'] })
            queryClient.invalidateQueries({ queryKey: ['categories'] })
            queryClient.invalidateQueries({ queryKey: ['tags'] })

            if (data.created > 0) {
                toast.success(`Successfully imported ${data.created} transactions`)
            }
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to execute import')
        },
    })
}
