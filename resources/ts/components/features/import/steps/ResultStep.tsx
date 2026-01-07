import { CheckCircle2, XCircle, Copy, Tag, FolderOpen } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useNavigate } from 'react-router-dom'
import type { ImportResult } from '@/types/import'

interface ResultStepProps {
    result: ImportResult
}

export function ResultStep({ result }: ResultStepProps) {
    const navigate = useNavigate()

    const hasCreated = result.created > 0
    const hasErrors = result.errors.length > 0

    return (
        <div className="space-y-6">
            {/* Main Result */}
            <div className={`p-6 border rounded-lg text-center ${hasCreated ? 'bg-green-500/10' : 'bg-yellow-500/10'}`}>
                {hasCreated ? (
                    <CheckCircle2 className="size-16 text-green-500 mx-auto mb-4" />
                ) : (
                    <XCircle className="size-16 text-yellow-500 mx-auto mb-4" />
                )}
                <h2 className="text-2xl font-bold mb-2">
                    {hasCreated ? 'Import Complete!' : 'No Transactions Imported'}
                </h2>
                <p className="text-muted-foreground">
                    {hasCreated
                        ? `Successfully imported ${result.created} transaction${result.created !== 1 ? 's' : ''}`
                        : 'All transactions were either duplicates or had errors'}
                </p>
            </div>

            {/* Statistics */}
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="p-4 border rounded-lg text-center">
                    <div className="text-3xl font-bold text-green-600">{result.created}</div>
                    <div className="text-sm text-muted-foreground">Created</div>
                </div>
                <div className="p-4 border rounded-lg text-center">
                    <div className="text-3xl font-bold text-yellow-600">{result.skippedDuplicates}</div>
                    <div className="text-sm text-muted-foreground">Skipped (duplicates)</div>
                </div>
                <div className="p-4 border rounded-lg text-center">
                    <div className="text-3xl font-bold text-red-600">{result.errors.length}</div>
                    <div className="text-sm text-muted-foreground">Errors</div>
                </div>
            </div>

            {/* Created Entities */}
            {(result.createdCategories.length > 0 ||
                result.createdTags.length > 0 ||
                result.createdCurrencies.length > 0) && (
                <div className="p-4 border rounded-lg">
                    <h3 className="font-medium mb-3">New Entities Created</h3>
                    <div className="space-y-3">
                        {result.createdCategories.length > 0 && (
                            <div className="flex items-start gap-2">
                                <FolderOpen className="size-4 text-muted-foreground mt-0.5" />
                                <div>
                                    <div className="text-sm font-medium">Categories</div>
                                    <div className="flex flex-wrap gap-1 mt-1">
                                        {result.createdCategories.map((cat) => (
                                            <Badge key={cat} variant="outline">{cat}</Badge>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                        {result.createdTags.length > 0 && (
                            <div className="flex items-start gap-2">
                                <Tag className="size-4 text-muted-foreground mt-0.5" />
                                <div>
                                    <div className="text-sm font-medium">Tags</div>
                                    <div className="flex flex-wrap gap-1 mt-1">
                                        {result.createdTags.map((tag) => (
                                            <Badge key={tag} variant="outline">{tag}</Badge>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Errors */}
            {hasErrors && (
                <div className="p-4 border rounded-lg bg-red-500/10">
                    <div className="flex items-center gap-2 mb-2">
                        <XCircle className="size-4 text-red-500" />
                        <span className="font-medium">Errors ({result.errors.length})</span>
                    </div>
                    <div className="max-h-[200px] overflow-y-auto">
                        <ul className="text-sm space-y-1">
                            {result.errors.map((err, i) => (
                                <li key={i}>
                                    <span className="font-mono text-muted-foreground">Row {err.row}:</span>{' '}
                                    {err.message}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            )}

            {/* Actions */}
            <div className="flex justify-center gap-4">
                <Button variant="outline" onClick={() => navigate('/transactions')}>
                    View Transactions
                </Button>
            </div>
        </div>
    )
}
