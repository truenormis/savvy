import { apiClient } from './client'
import type {
    CsvParseResult,
    ColumnMapping,
    ImportOptions,
    ImportPreviewResult,
    ImportResult,
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

export const importApi = {
    parse: async (file: File): Promise<CsvParseResult> => {
        const formData = new FormData()
        formData.append('file', file)

        const response = await apiClient.post(`${ENDPOINT}/parse`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })

        return parseResultFromSnakeCase(response.data.data)
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

    import: async (
        importId: string,
        mapping: ColumnMapping,
        options: ImportOptions
    ): Promise<ImportResult> => {
        const response = await apiClient.post(`${ENDPOINT}`, {
            import_id: importId,
            mapping: toSnakeCase(mapping),
            options: optionsToSnakeCase(options),
        })

        return importResultFromSnakeCase(response.data.data)
    },
}
