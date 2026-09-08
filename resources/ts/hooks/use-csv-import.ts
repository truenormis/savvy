import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { importApi } from '@/api/import'
import { toast } from 'sonner'
import type {
    ColumnMapping,
    ImportOptions,
    CsvParseResult,
    ImportResult,
} from '@/types/import'

export interface ImportProgress {
    processed: number
    total: number
    created: number
    skipped: number
    errors: number
}

export function useParseImport() {
    return useMutation({
        mutationFn: async (uploadId: string): Promise<CsvParseResult> => {
            const dispatched = await importApi.parse(uploadId)
            const state = await importApi.poll(dispatched.importId, (s) => s.status === 'parsed')

            if (!state.parse) {
                throw new Error('Parsing produced no result')
            }

            return { ...state.parse, importId: state.importId }
        },
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
    const [progress, setProgress] = useState<ImportProgress | null>(null)

    const mutation = useMutation({
        mutationFn: async ({
            importId,
            mapping,
            options,
        }: {
            importId: string
            mapping: ColumnMapping
            options: ImportOptions
        }): Promise<ImportResult> => {
            setProgress({ processed: 0, total: 0, created: 0, skipped: 0, errors: 0 })

            const dispatched = await importApi.execute(importId, mapping, options)
            const state = await importApi.poll(
                dispatched.importId,
                (s) => s.status === 'completed',
                (s) =>
                    setProgress({
                        processed: s.processedRows,
                        total: s.totalRows ?? 0,
                        created: s.created,
                        skipped: s.skipped,
                        errors: s.errors,
                    })
            )

            if (!state.result) {
                throw new Error('Import produced no result')
            }

            return state.result
        },
        onSuccess: (data) => {
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

    return { ...mutation, progress }
}
