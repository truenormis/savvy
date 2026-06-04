import { apiClient } from './client'
import type {
    CsvParseResult,
    ColumnMapping,
    ImportOptions,
    ImportPreviewResult,
    ImportResult,
    ImportState,
} from '@/types/import'

const ENDPOINT = '/transactions/import'

// Convert frontend camelCase to backend snake_case
const toSnakeCase = (mapping: ColumnMapping) => ({
    date: mapping.date,
    amount: mapping.amount,
    description: mapping.description,
    type: mapping.type,
    category: mapping.category,
    tags: mapping.tags,
    currency: mapping.currency,
})

const optionsToSnakeCase = (options: ImportOptions) => ({
    date_format: options.dateFormat,
    amount_format: options.amountFormat,
    default_account_id: options.defaultAccountId,
    default_type: options.defaultType,
    skip_first_row: options.skipFirstRow,
    create_missing_currencies: options.createMissingCurrencies,
    create_missing_tags: options.createMissingTags,
    create_missing_categories: options.createMissingCategories,
})

// Convert backend snake_case to frontend camelCase
const parseResultFromSnakeCase = (data: Record<string, unknown>): CsvParseResult => ({
    importId: data.import_id as string,
    headers: data.headers as string[],
    previewRows: data.preview_rows as string[][],
    totalRows: data.total_rows as number,
    detectedFormats: {
        dateFormat: (data.detected_formats as Record<string, unknown>).date_format as CsvParseResult['detectedFormats']['dateFormat'],
        amountFormat: (data.detected_formats as Record<string, unknown>).amount_format as CsvParseResult['detectedFormats']['amountFormat'],
        hasHeader: (data.detected_formats as Record<string, unknown>).has_header as boolean,
        delimiter: (data.detected_formats as Record<string, unknown>).delimiter as string,
    },
    suggestedMapping: data.suggested_mapping as CsvParseResult['suggestedMapping'],
})

const previewResultFromSnakeCase = (data: Record<string, unknown>): ImportPreviewResult => ({
    previewTransactions: (data.preview_transactions as Record<string, unknown>[]).map((t) => ({
        row: t.row as number,
        date: t.date as string,
        type: t.type as string,
        amount: t.amount as number,
        description: t.description as string | null,
        category: t.category as string | null,
        tags: t.tags as string[],
        status: t.status as 'new' | 'duplicate' | 'error',
        duplicateOf: t.duplicate_of as number | null,
        warnings: t.warnings as string[],
        error: t.error as string | null,
    })),
    summary: {
        willCreate: (data.summary as Record<string, unknown>).will_create as number,
        willSkip: (data.summary as Record<string, unknown>).will_skip as number,
        hasErrors: (data.summary as Record<string, unknown>).has_errors as number,
        totalRows: ((data.summary as Record<string, unknown>).total_rows as number | null) ?? null,
        sampled: ((data.summary as Record<string, unknown>).sampled as number) ?? 0,
        currenciesToCreate: (data.summary as Record<string, unknown>).currencies_to_create as string[],
        tagsToCreate: (data.summary as Record<string, unknown>).tags_to_create as string[],
        categoriesToCreate: (data.summary as Record<string, unknown>).categories_to_create as string[],
    },
})

const importResultFromSnakeCase = (data: Record<string, unknown>): ImportResult => ({
    created: data.created as number,
    skippedDuplicates: data.skipped_duplicates as number,
    errors: (data.errors as Record<string, unknown>[]).map((e) => ({
        row: e.row as number,
        message: e.message as string,
    })),
    createdCurrencies: data.created_currencies as string[],
    createdTags: data.created_tags as string[],
    createdCategories: data.created_categories as string[],
})

const importStateFromSnakeCase = (data: Record<string, unknown>): ImportState => ({
    importId: data.import_id as string,
    status: data.status as ImportState['status'],
    totalRows: (data.total_rows as number | null) ?? null,
    processedRows: (data.processed_rows as number) ?? 0,
    created: (data.created as number) ?? 0,
    skipped: (data.skipped as number) ?? 0,
    errors: (data.errors as number) ?? 0,
    message: (data.message as string | null) ?? null,
    parse: data.parse ? parseResultFromSnakeCase(data.parse as Record<string, unknown>) : null,
    result: data.result ? importResultFromSnakeCase(data.result as Record<string, unknown>) : null,
})

const delay = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))

export const importApi = {
    parse: async (uploadId: string): Promise<ImportState> => {
        const response = await apiClient.post(`${ENDPOINT}/parse`, { upload_id: uploadId })

        return importStateFromSnakeCase(response.data.data)
    },

    status: async (importId: string): Promise<ImportState> => {
        const response = await apiClient.get(`${ENDPOINT}/${importId}`)

        return importStateFromSnakeCase(response.data.data)
    },

    preview: async (
        importId: string,
        mapping: ColumnMapping,
        options: ImportOptions
    ): Promise<ImportPreviewResult> => {
        const response = await apiClient.post(`${ENDPOINT}/preview`, {
            import_id: importId,
            mapping: toSnakeCase(mapping),
            options: optionsToSnakeCase(options),
        })

        return previewResultFromSnakeCase(response.data.data)
    },

    execute: async (
        importId: string,
        mapping: ColumnMapping,
        options: ImportOptions
    ): Promise<ImportState> => {
        const response = await apiClient.post(`${ENDPOINT}/execute`, {
            import_id: importId,
            mapping: toSnakeCase(mapping),
            options: optionsToSnakeCase(options),
        })

        return importStateFromSnakeCase(response.data.data)
    },

    poll: async (
        importId: string,
        until: (state: ImportState) => boolean,
        onTick?: (state: ImportState) => void,
        intervalMs = 1000
    ): Promise<ImportState> => {
        while (true) {
            const state = await importApi.status(importId)
            onTick?.(state)

            if (state.status === 'failed') {
                throw new Error(state.message || 'Import failed')
            }

            if (until(state)) {
                return state
            }

            await delay(intervalMs)
        }
    },
}
