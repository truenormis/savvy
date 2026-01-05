import { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { TrendingUp, TrendingDown, AlertTriangle, ChevronRight } from 'lucide-react'
import { useExpensesByCategory } from '@/hooks'
import type { ReportFilters } from '../types'

interface ExpensesByCategoryProps {
    filters: ReportFilters
}

export function ExpensesByCategory({ filters }: ExpensesByCategoryProps) {
    const navigate = useNavigate()
    const { data, isLoading, error } = useExpensesByCategory(filters)

    const maxValue = useMemo(() => {
        if (!data?.categories.length) return 0
        return Math.max(...data.categories.flatMap(c => [c.current, c.previous]))
    }, [data])

    const formatCurrency = (val: number, currency: string) => {
        return `${currency}${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
    }

    const handleCategoryClick = (categoryId: number) => {
        navigate(`/transactions?category_ids=${categoryId}`)
    }

    if (error) {
        return (
            <Card>
                <CardContent className="py-8 text-center text-red-500">
                    Failed to load expenses by category
                </CardContent>
            </Card>
        )
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-lg">Expenses by Category</CardTitle>
                <p className="text-sm text-muted-foreground">
                    Compare spending across categories
                </p>
            </CardHeader>
            <CardContent className="space-y-3">
                {isLoading ? (
                    <div className="space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <Skeleton key={i} className="h-16" />
                        ))}
                    </div>
                ) : !data?.categories.length ? (
                    <div className="py-8 text-center text-muted-foreground">
                        No expense data for selected period
                    </div>
                ) : (
                    data.categories.map((category) => {
                        const change = category.previous > 0
                            ? ((category.current - category.previous) / category.previous) * 100
                            : 0
                        const isIncrease = change > 0
                        const isAnomalous = Math.abs(change) > 50
                        const currentWidth = maxValue > 0 ? (category.current / maxValue) * 100 : 0
                        const previousWidth = maxValue > 0 ? (category.previous / maxValue) * 100 : 0

                        return (
                            <div
                                key={category.id}
                                className="group"
                            >
                                <div
                                    className="flex items-center gap-3 p-2 rounded-lg hover:bg-muted/50 cursor-pointer transition-colors"
                                    onClick={() => handleCategoryClick(category.id)}
                                >
                                    {/* Icon */}
                                    <div
                                        className="flex items-center justify-center size-8 rounded-lg flex-shrink-0"
                                        style={{ backgroundColor: category.color }}
                                    >
                                        <span className="text-base">{category.icon}</span>
                                    </div>

                                    {/* Content */}
                                    <div className="flex-1 min-w-0">
                                        {/* Header row */}
                                        <div className="flex items-center justify-between mb-1.5">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-sm">{category.name}</span>
                                                {isAnomalous && filters.compareWith !== 'none' && (
                                                    <AlertTriangle className="size-4 text-amber-500" />
                                                )}
                                            </div>
                                            <div className="flex items-center gap-3 text-sm">
                                                <span className="font-semibold">
                                                    {formatCurrency(category.current, data.currency)}
                                                </span>
                                                {filters.compareWith !== 'none' && category.previous > 0 && (
                                                    <>
                                                        <span className="text-muted-foreground">
                                                            vs {formatCurrency(category.previous, data.currency)}
                                                        </span>
                                                        <span className={cn(
                                                            'flex items-center gap-0.5 text-xs font-medium',
                                                            isIncrease ? 'text-red-600' : 'text-green-600'
                                                        )}>
                                                            {isIncrease ? (
                                                                <TrendingUp className="size-3" />
                                                            ) : (
                                                                <TrendingDown className="size-3" />
                                                            )}
                                                            {Math.abs(change).toFixed(0)}%
                                                        </span>
                                                    </>
                                                )}
                                            </div>
                                        </div>

                                        {/* Bars */}
                                        <div className="space-y-1">
                                            {/* Current period bar */}
                                            <div className="h-2.5 bg-muted rounded-full overflow-hidden">
                                                <div
                                                    className="h-full rounded-full transition-all duration-500"
                                                    style={{
                                                        width: `${currentWidth}%`,
                                                        backgroundColor: category.color,
                                                    }}
                                                />
                                            </div>
                                            {/* Previous period bar (if comparing) */}
                                            {filters.compareWith !== 'none' && category.previous > 0 && (
                                                <div className="h-1.5 bg-muted rounded-full overflow-hidden">
                                                    <div
                                                        className="h-full rounded-full transition-all duration-500 opacity-40"
                                                        style={{
                                                            width: `${previousWidth}%`,
                                                            backgroundColor: category.color,
                                                        }}
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Chevron */}
                                    <ChevronRight className="size-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0" />
                                </div>
                            </div>
                        )
                    })
                )}
            </CardContent>
        </Card>
    )
}
