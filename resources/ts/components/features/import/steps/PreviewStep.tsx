import { CheckCircle2, XCircle, AlertTriangle, Copy } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { ImportPreviewResult } from '@/types/import'

interface PreviewStepProps {
    previewResult: ImportPreviewResult
    isLoading: boolean
}

export function PreviewStep({ previewResult, isLoading }: PreviewStepProps) {
    const { previewTransactions, summary } = previewResult

    const statusConfig = {
        new: {
            icon: CheckCircle2,
            color: 'text-green-500',
            bgColor: 'bg-green-500/10',
            label: 'New',
        },
        duplicate: {
            icon: Copy,
            color: 'text-yellow-500',
            bgColor: 'bg-yellow-500/10',
            label: 'Duplicate',
        },
        error: {
            icon: XCircle,
            color: 'text-red-500',
            bgColor: 'bg-red-500/10',
            label: 'Error',
        },
    }

    return (
        <div className={cn('space-y-6', isLoading && 'opacity-50 pointer-events-none')}>
            {/* Summary Cards */}
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="p-4 border rounded-lg bg-green-500/10">
                    <div className="text-3xl font-bold text-green-600">{summary.willCreate}</div>
                    <div className="text-sm text-muted-foreground">Will be created</div>
                </div>
                <div className="p-4 border rounded-lg bg-yellow-500/10">
                    <div className="text-3xl font-bold text-yellow-600">{summary.willSkip}</div>
                    <div className="text-sm text-muted-foreground">Duplicates (skipped)</div>
                </div>
                <div className="p-4 border rounded-lg bg-red-500/10">
                    <div className="text-3xl font-bold text-red-600">{summary.hasErrors}</div>
                    <div className="text-sm text-muted-foreground">Errors</div>
                </div>
            </div>

            {/* Auto-create info */}
            {(summary.categoriesToCreate.length > 0 ||
                summary.tagsToCreate.length > 0 ||
                summary.currenciesToCreate.length > 0) && (
                <div className="p-4 border rounded-lg bg-blue-500/10">
                    <div className="flex items-center gap-2 mb-2">
                        <AlertTriangle className="size-4 text-blue-500" />
                        <span className="font-medium">New entities will be created:</span>
                    </div>
                    <div className="space-y-2 text-sm">
                        {summary.categoriesToCreate.length > 0 && (
                            <div className="flex flex-wrap gap-1">
                                <span className="text-muted-foreground">Categories:</span>
                                {summary.categoriesToCreate.map((cat) => (
                                    <Badge key={cat} variant="outline">{cat}</Badge>
                                ))}
                            </div>
                        )}
                        {summary.tagsToCreate.length > 0 && (
                            <div className="flex flex-wrap gap-1">
                                <span className="text-muted-foreground">Tags:</span>
                                {summary.tagsToCreate.map((tag) => (
                                    <Badge key={tag} variant="outline">{tag}</Badge>
                                ))}
                            </div>
                        )}
                        {summary.currenciesToCreate.length > 0 && (
                            <div className="flex flex-wrap gap-1">
                                <span className="text-muted-foreground">Currencies:</span>
                                {summary.currenciesToCreate.map((cur) => (
                                    <Badge key={cur} variant="outline">{cur}</Badge>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Transactions Preview Table */}
            <div className="border rounded-lg overflow-hidden">
                <div className="overflow-x-auto max-h-[400px] overflow-y-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-muted sticky top-0">
                            <tr>
                                <th className="px-4 py-2 text-left font-medium w-16">Row</th>
                                <th className="px-4 py-2 text-left font-medium w-24">Status</th>
                                <th className="px-4 py-2 text-left font-medium w-28">Date</th>
                                <th className="px-4 py-2 text-left font-medium w-20">Type</th>
                                <th className="px-4 py-2 text-right font-medium w-28">Amount</th>
                                <th className="px-4 py-2 text-left font-medium">Description</th>
                                <th className="px-4 py-2 text-left font-medium w-32">Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            {previewTransactions.map((tx) => {
                                const config = statusConfig[tx.status]
                                const StatusIcon = config.icon

                                return (
                                    <tr key={tx.row} className={cn('border-t', config.bgColor)}>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {tx.row}
                                        </td>
                                        <td className="px-4 py-2">
                                            <div className="flex items-center gap-1">
                                                <StatusIcon className={cn('size-4', config.color)} />
                                                <span className={config.color}>{config.label}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-2 font-mono">
                                            {tx.date || '-'}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Badge variant={tx.type === 'income' ? 'default' : 'secondary'}>
                                                {tx.type}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono">
                                            {tx.amount?.toFixed(2) || '-'}
                                        </td>
                                        <td className="px-4 py-2 truncate max-w-[200px]">
                                            {tx.description || '-'}
                                        </td>
                                        <td className="px-4 py-2 truncate max-w-[150px]">
                                            {tx.category || '-'}
                                        </td>
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Warnings */}
            {previewTransactions.some((tx) => tx.warnings.length > 0) && (
                <div className="p-4 border rounded-lg bg-yellow-500/10">
                    <div className="flex items-center gap-2 mb-2">
                        <AlertTriangle className="size-4 text-yellow-500" />
                        <span className="font-medium">Warnings:</span>
                    </div>
                    <ul className="text-sm space-y-1">
                        {previewTransactions
                            .filter((tx) => tx.warnings.length > 0)
                            .slice(0, 10)
                            .map((tx) => (
                                <li key={tx.row}>
                                    Row {tx.row}: {tx.warnings.join(', ')}
                                </li>
                            ))}
                    </ul>
                </div>
            )}

            {/* Errors */}
            {previewTransactions.some((tx) => tx.error) && (
                <div className="p-4 border rounded-lg bg-red-500/10">
                    <div className="flex items-center gap-2 mb-2">
                        <XCircle className="size-4 text-red-500" />
                        <span className="font-medium">Errors (will be skipped):</span>
                    </div>
                    <ul className="text-sm space-y-1">
                        {previewTransactions
                            .filter((tx) => tx.error)
                            .slice(0, 10)
                            .map((tx) => (
                                <li key={tx.row}>
                                    Row {tx.row}: {tx.error}
                                </li>
                            ))}
                    </ul>
                </div>
            )}
        </div>
    )
}
