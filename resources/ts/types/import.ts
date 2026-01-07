export type DateFormat = 'ISO' | 'DD.MM.YYYY' | 'MM/DD/YYYY' | 'DD/MM/YYYY'
export type AmountFormat = 'US' | 'EU'

export interface DetectedFormats {
    dateFormat: DateFormat
    amountFormat: AmountFormat
    hasHeader: boolean
    delimiter: string
}

export interface SuggestedMapping {
    date?: number
    amount?: number
    description?: number
    category?: number
    tags?: number
    currency?: number
    type?: number
}

export interface CsvParseResult {
    importId: string
    headers: string[]
    previewRows: string[][]
    totalRows: number
    detectedFormats: DetectedFormats
    suggestedMapping: SuggestedMapping
}

export interface ColumnMapping {
    date: number
    amount: number
    description: number | null
    type: number | null
    category: number | null
    tags: number | null
    currency: number | null
}

export interface ImportOptions {
    dateFormat: DateFormat
    amountFormat: AmountFormat
    defaultAccountId: number
    defaultType: 'income' | 'expense'
    skipFirstRow: boolean
    createMissingCurrencies: boolean
    createMissingTags: boolean
    createMissingCategories: boolean
}

export interface PreviewTransaction {
    row: number
    date: string
    type: string
    amount: number
    description: string | null
    category: string | null
    tags: string[]
    status: 'new' | 'duplicate' | 'error'
    duplicateOf: number | null
    warnings: string[]
    error: string | null
}

export interface ImportPreviewSummary {
    willCreate: number
    willSkip: number
    hasErrors: number
    currenciesToCreate: string[]
    tagsToCreate: string[]
    categoriesToCreate: string[]
}

export interface ImportPreviewResult {
    previewTransactions: PreviewTransaction[]
    summary: ImportPreviewSummary
}

export interface ImportError {
    row: number
    message: string
}

export interface ImportResult {
    created: number
    skippedDuplicates: number
    errors: ImportError[]
    createdCurrencies: string[]
    createdTags: string[]
    createdCategories: string[]
}

export type ImportStep = 'upload' | 'mapping' | 'preview' | 'result'
