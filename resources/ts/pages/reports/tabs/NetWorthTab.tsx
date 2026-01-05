import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { TrendingUp, TrendingDown, Wallet } from 'lucide-react'
import { NetWorthChart } from '../components/NetWorthChart'
import { useNetWorth } from '@/hooks'
import { ACCOUNT_TYPE_CONFIG } from '@/constants'
import type { ReportFilters } from '../types'
import type { AccountType } from '@/types'

interface NetWorthTabProps {
    filters: ReportFilters
}

export function NetWorthTab({ filters }: NetWorthTabProps) {
    const { data, isLoading } = useNetWorth(filters)

    const isPositive = (data?.change ?? 0) >= 0

    const formatCurrency = (val: number, currency: string = '$') => {
        return `${currency}${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
    }

    return (
        <div className="space-y-6">
            {/* Block 1 — Current Net Worth */}
            <Card>
                <CardContent className="pt-6">
                    {isLoading ? (
                        <div className="flex flex-col items-center text-center py-4">
                            <Skeleton className="size-16 rounded-2xl mb-4" />
                            <Skeleton className="h-4 w-32 mb-2" />
                            <Skeleton className="h-16 w-48 mb-4" />
                            <Skeleton className="h-8 w-64" />
                        </div>
                    ) : data ? (
                        <div className="flex flex-col items-center text-center py-4">
                            {/* Icon */}
                            <div className="flex items-center justify-center size-16 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 text-white mb-4 shadow-lg">
                                <Wallet className="size-8" />
                            </div>

                            {/* Label */}
                            <p className="text-sm text-muted-foreground font-medium mb-2">
                                Current Net Worth
                            </p>

                            {/* Main value */}
                            <p className={cn(
                                'text-6xl font-bold tracking-tight mb-4',
                                data.current >= 0 ? 'text-blue-600' : 'text-red-600'
                            )}>
                                {formatCurrency(data.current, data.currency)}
                            </p>

                            {/* Change indicators */}
                            {filters.compareWith !== 'none' && data.previous !== null && (
                                <div className="flex items-center gap-4">
                                    {/* Percentage change */}
                                    <div className={cn(
                                        'flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium',
                                        isPositive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                    )}>
                                        {isPositive ? (
                                            <TrendingUp className="size-4" />
                                        ) : (
                                            <TrendingDown className="size-4" />
                                        )}
                                        {isPositive ? '+' : ''}{data.changePercent.toFixed(1)}%
                                    </div>

                                    {/* Absolute change */}
                                    <div className={cn(
                                        'flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium',
                                        isPositive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                    )}>
                                        {isPositive ? '+' : ''}{formatCurrency(data.change, data.currency)}
                                    </div>

                                    <span className="text-sm text-muted-foreground">
                                        vs previous period
                                    </span>
                                </div>
                            )}
                        </div>
                    ) : null}
                </CardContent>
            </Card>

            {/* Block 2 — Net Worth Chart */}
            <NetWorthChart filters={filters} />

            {/* Block 3 — Accounts Breakdown */}
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-lg">Accounts Breakdown</CardTitle>
                    <p className="text-sm text-muted-foreground">
                        Distribution by account
                    </p>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="space-y-2">
                            {Array.from({ length: 3 }).map((_, i) => (
                                <Skeleton key={i} className="h-14" />
                            ))}
                        </div>
                    ) : !data?.accounts?.length ? (
                        <div className="h-[150px] flex items-center justify-center text-muted-foreground">
                            No accounts found
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {data?.accounts.map(account => (
                                <div
                                    key={account.id}
                                    className="flex items-center gap-3 p-3 rounded-lg bg-muted/30"
                                >
                                    {/* Icon */}
                                    {(() => {
                                        const config = ACCOUNT_TYPE_CONFIG[account.type as AccountType]
                                        const Icon = config?.icon || Wallet
                                        return (
                                            <div className={cn('flex items-center justify-center size-10 rounded-lg', config?.color || 'bg-muted')}>
                                                <Icon className="size-4" />
                                            </div>
                                        )
                                    })()}

                                    {/* Name and type */}
                                    <div className="flex-1 min-w-0">
                                        <p className="font-medium text-sm truncate">
                                            {account.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground capitalize">
                                            {account.type}
                                        </p>
                                    </div>

                                    {/* Balance and percentage */}
                                    <div className="text-right">
                                        <p className="font-semibold">
                                            {formatCurrency(account.balance, data.currency)}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {account.percentage}%
                                        </p>
                                    </div>

                                    {/* Progress bar */}
                                    <div className="w-20 h-2 bg-muted rounded-full overflow-hidden">
                                        <div
                                            className="h-full bg-blue-500 rounded-full"
                                            style={{ width: `${Math.max(account.percentage, 2)}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    )
}
