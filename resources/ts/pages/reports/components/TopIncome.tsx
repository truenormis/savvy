import { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { ChevronRight } from 'lucide-react'
import { useTransactionReportTop } from '@/hooks'
import type { ReportFilters } from '../types'

interface TopIncomeProps {
    filters: ReportFilters
    limit?: number
}

export function TopIncome({ filters, limit = 10 }: TopIncomeProps) {
    const navigate = useNavigate()
    const { data, isLoading } = useTransactionReportTop(filters, 'income', limit)

    const transactions = data?.items || []
    const currency = data?.currency || '$'

    const totalTop = useMemo(() => {
        return transactions.reduce((sum, t) => sum + t.amount, 0)
    }, [transactions])

    const formatCurrency = (val: number) => {
        return `${currency}${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
    }

    const formatDate = (dateStr: string) => {
        if (!dateStr) return ''
        const parts = dateStr.split('-')
        if (parts.length !== 3) return dateStr
        const [year, month, day] = parts.map(Number)
        const date = new Date(year, month - 1, day)
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    }

    const handleTransactionClick = (id: number) => {
        navigate(`/transactions?id=${id}`)
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-lg">Top Income</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Largest income transactions this period
                        </p>
                    </div>
                    {!isLoading && transactions.length > 0 && (
                        <div className="text-right">
                            <p className="text-sm text-muted-foreground">Top {transactions.length} total</p>
                            <p className="text-lg font-semibold text-green-600">{formatCurrency(totalTop)}</p>
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <div className="space-y-2">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <Skeleton key={i} className="h-14" />
                        ))}
                    </div>
                ) : transactions.length === 0 ? (
                    <div className="h-[200px] flex items-center justify-center text-muted-foreground">
                        No income for selected period
                    </div>
                ) : (
                    <div className="space-y-1">
                        {transactions.map((transaction, index) => (
                            <div
                                key={transaction.id}
                                className="group flex items-center gap-3 p-2 rounded-lg hover:bg-muted/50 cursor-pointer transition-colors"
                                onClick={() => handleTransactionClick(transaction.id)}
                            >
                                {/* Rank */}
                                <div className="flex items-center justify-center size-6 rounded-full bg-muted text-xs font-medium text-muted-foreground">
                                    {index + 1}
                                </div>

                                {/* Category icon */}
                                <div
                                    className="flex items-center justify-center size-9 rounded-lg flex-shrink-0"
                                    style={{ backgroundColor: transaction.category.color }}
                                >
                                    <span className="text-lg">{transaction.category.icon}</span>
                                </div>

                                {/* Description and details */}
                                <div className="flex-1 min-w-0">
                                    <p className="font-medium text-sm truncate">
                                        {transaction.description || transaction.category.name}
                                    </p>
                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>{formatDate(transaction.date)}</span>
                                        <span>•</span>
                                        <span>{transaction.category.name}</span>
                                        <span>•</span>
                                        <span>{transaction.account.name}</span>
                                    </div>
                                </div>

                                {/* Amount */}
                                <div className="text-right flex-shrink-0">
                                    <p className="font-semibold text-green-600">
                                        +{formatCurrency(transaction.amount)}
                                    </p>
                                </div>

                                {/* Chevron */}
                                <ChevronRight className="size-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0" />
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
