import { useCallback, useState } from 'react'
import { Upload, FileText, AlertCircle, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { CsvParseResult } from '@/types/import'

interface UploadStepProps {
    onFileSelect: (file: File) => void
    parseResult: CsvParseResult | null
    isLoading: boolean
    error: string | null
}

export function UploadStep({ onFileSelect, parseResult, isLoading, error }: UploadStepProps) {
    const [dragActive, setDragActive] = useState(false)
    const [selectedFile, setSelectedFile] = useState<File | null>(null)

    const handleDrag = useCallback((e: React.DragEvent) => {
        e.preventDefault()
        e.stopPropagation()
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true)
        } else if (e.type === 'dragleave') {
            setDragActive(false)
        }
    }, [])

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault()
        e.stopPropagation()
        setDragActive(false)

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            const file = e.dataTransfer.files[0]
            if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
                setSelectedFile(file)
                onFileSelect(file)
            }
        }
    }, [onFileSelect])

    const handleFileSelect = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            const file = e.target.files[0]
            setSelectedFile(file)
            onFileSelect(file)
        }
    }, [onFileSelect])

    const clearFile = useCallback(() => {
        setSelectedFile(null)
    }, [])

    return (
        <div className="space-y-6">
            {/* Drop Zone */}
            <div
                className={cn(
                    'border-2 border-dashed rounded-lg p-8 text-center transition-colors',
                    dragActive ? 'border-primary bg-primary/5' : 'border-muted-foreground/25',
                    isLoading && 'opacity-50 pointer-events-none'
                )}
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
            >
                <input
                    type="file"
                    accept=".csv"
                    onChange={handleFileSelect}
                    className="hidden"
                    id="csv-upload"
                    disabled={isLoading}
                />

                {selectedFile ? (
                    <div className="flex flex-col items-center gap-2">
                        <FileText className="size-12 text-primary" />
                        <div className="flex items-center gap-2">
                            <span className="font-medium">{selectedFile.name}</span>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-6"
                                onClick={clearFile}
                                disabled={isLoading}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                        <span className="text-sm text-muted-foreground">
                            {(selectedFile.size / 1024).toFixed(1)} KB
                        </span>
                    </div>
                ) : (
                    <label htmlFor="csv-upload" className="cursor-pointer">
                        <div className="flex flex-col items-center gap-2">
                            <Upload className="size-12 text-muted-foreground" />
                            <div className="text-lg font-medium">
                                Drop CSV file here or click to browse
                            </div>
                            <div className="text-sm text-muted-foreground">
                                Maximum file size: 5MB
                            </div>
                        </div>
                    </label>
                )}
            </div>

            {/* Error Message */}
            {error && (
                <div className="flex items-center gap-2 p-4 bg-destructive/10 text-destructive rounded-lg">
                    <AlertCircle className="size-5" />
                    <span>{error}</span>
                </div>
            )}

            {/* Parse Result Preview */}
            {parseResult && (
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h3 className="font-medium">File Preview</h3>
                        <span className="text-sm text-muted-foreground">
                            {parseResult.totalRows} rows detected
                        </span>
                    </div>

                    {/* Detected Formats */}
                    <div className="flex gap-4 text-sm">
                        <div className="flex items-center gap-2">
                            <span className="text-muted-foreground">Date format:</span>
                            <span className="font-mono bg-muted px-2 py-0.5 rounded">
                                {parseResult.detectedFormats.dateFormat}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-muted-foreground">Amount format:</span>
                            <span className="font-mono bg-muted px-2 py-0.5 rounded">
                                {parseResult.detectedFormats.amountFormat}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-muted-foreground">Delimiter:</span>
                            <span className="font-mono bg-muted px-2 py-0.5 rounded">
                                {parseResult.detectedFormats.delimiter === '\t' ? 'TAB' : parseResult.detectedFormats.delimiter}
                            </span>
                        </div>
                    </div>

                    {/* Preview Table */}
                    <div className="border rounded-lg overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted">
                                    <tr>
                                        {parseResult.headers.map((header, i) => (
                                            <th key={i} className="px-4 py-2 text-left font-medium">
                                                {header}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {parseResult.previewRows.slice(0, 5).map((row, i) => (
                                        <tr key={i} className="border-t">
                                            {row.map((cell, j) => (
                                                <td key={j} className="px-4 py-2 truncate max-w-[200px]">
                                                    {cell}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {parseResult.previewRows.length > 5 && (
                            <div className="px-4 py-2 text-sm text-muted-foreground bg-muted/50 border-t">
                                ... and {parseResult.totalRows - 5} more rows
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    )
}
