import { useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { useActivityHeatmap } from '@/hooks'
import type { ReportFilters } from '../types'

interface ActivityHeatmapProps {
    filters: ReportFilters
}

export function ActivityHeatmap({ filters }: ActivityHeatmapProps) {
    const { data, isLoading, error } = useActivityHeatmap(filters)

    // Parse date string without timezone issues
    const parseLocalDate = (dateStr: string) => {
        const [year, month, day] = dateStr.split('-').map(Number)
        return new Date(year, month - 1, day)
    }

    // Format date as YYYY-MM-DD without timezone conversion
    const formatDateKey = (year: number, month: number, day: number) => {
        return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    }

    const heatmapData = useMemo(() => {
        if (!data?.items?.length) return null

        const { items, max, currency } = data

        // Get the year and month from the first item (parse without timezone)
        const firstDate = parseLocalDate(items[0].date)
        const year = firstDate.getFullYear()
        const month = firstDate.getMonth()

        // Calculate the range for this month
        const startOfMonth = new Date(year, month, 1)
        const endOfMonth = new Date(year, month + 1, 0)

        // Get day of week for the first day (0 = Sunday)
        const startDayOfWeek = startOfMonth.getDay()
        const daysInMonth = endOfMonth.getDate()

        // Build lookup from API data
        const dataByDate: Record<string, { value: number; count: number }> = {}
        items.forEach(item => {
            dataByDate[item.date] = { value: item.value, count: item.count }
        })

        // Build weeks array
        const weeks: Array<Array<{ day: number; value: number; count: number; date: string } | null>> = []
        let currentWeek: Array<{ day: number; value: number; count: number; date: string } | null> = []

        // Add empty cells for days before the first day of month
        for (let i = 0; i < startDayOfWeek; i++) {
            currentWeek.push(null)
        }

        // Fill in all days of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = formatDateKey(year, month, day)
            const dayData = dataByDate[dateStr] || { value: 0, count: 0 }

            currentWeek.push({
                day,
                value: dayData.value,
                count: dayData.count,
                date: dateStr,
            })

            if (currentWeek.length === 7) {
                weeks.push(currentWeek)
                currentWeek = []
            }
        }

        // Add remaining days to last week
        if (currentWeek.length > 0) {
            while (currentWeek.length < 7) {
                currentWeek.push(null)
            }
            weeks.push(currentWeek)
        }

        return {
            weeks,
            max,
            currency,
            monthName: firstDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
        }
    }, [data])

    const getIntensityClass = (value: number, max: number) => {
        if (value === 0) return 'bg-muted'
        const intensity = value / max
        if (intensity <= 0.25) return 'bg-amber-100 dark:bg-amber-900/30'
        if (intensity <= 0.5) return 'bg-amber-300 dark:bg-amber-700/50'
        if (intensity <= 0.75) return 'bg-orange-400 dark:bg-orange-600/60'
        return 'bg-red-500 dark:bg-red-500/70'
    }

    const formatCurrency = (val: number, currency: string) => {
        return `${currency}${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
    }

    const dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

    if (error) {
        return (
            <Card>
                <CardContent className="py-8 text-center text-red-500">
                    Failed to load activity heatmap
                </CardContent>
            </Card>
        )
    }

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-lg">Spending Activity</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Daily expense intensity
                        </p>
                    </div>
                    {heatmapData && (
                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span>Less</span>
                            <div className="flex gap-0.5">
                                <div className="size-3 rounded-sm bg-muted" />
                                <div className="size-3 rounded-sm bg-amber-100 dark:bg-amber-900/30" />
                                <div className="size-3 rounded-sm bg-amber-300 dark:bg-amber-700/50" />
                                <div className="size-3 rounded-sm bg-orange-400 dark:bg-orange-600/60" />
                                <div className="size-3 rounded-sm bg-red-500 dark:bg-red-500/70" />
                            </div>
                            <span>More</span>
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-[220px]" />
                ) : !heatmapData ? (
                    <div className="h-[220px] flex items-center justify-center text-muted-foreground">
                        No data for selected period
                    </div>
                ) : (
                    <div className="space-y-2">
                        {/* Day labels row */}
                        <div className="flex gap-1">
                            <div className="w-8" /> {/* Spacer for week labels */}
                            {dayLabels.map(day => (
                                <div
                                    key={day}
                                    className="flex-1 text-center text-xs text-muted-foreground"
                                >
                                    {day}
                                </div>
                            ))}
                        </div>

                        {/* Weeks */}
                        <div className="space-y-1">
                            {heatmapData.weeks.map((week, weekIndex) => (
                                <div key={weekIndex} className="flex gap-1">
                                    <div className="w-8 text-xs text-muted-foreground flex items-center">
                                        W{weekIndex + 1}
                                    </div>
                                    {week.map((day, dayIndex) => (
                                        <div
                                            key={dayIndex}
                                            className="flex-1 aspect-square"
                                        >
                                            {day ? (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div
                                                            className={cn(
                                                                'size-full rounded-md flex items-center justify-center text-xs font-medium cursor-default transition-colors',
                                                                'hover:ring-2 hover:ring-ring hover:ring-offset-1',
                                                                getIntensityClass(day.value, heatmapData.max),
                                                                day.value > 0 && day.value / heatmapData.max > 0.5
                                                                    ? 'text-white'
                                                                    : 'text-foreground'
                                                            )}
                                                        >
                                                            {day.day}
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top" className="text-center">
                                                        <p className="font-medium">
                                                            {parseLocalDate(day.date).toLocaleDateString('en-US', {
                                                                weekday: 'short',
                                                                month: 'short',
                                                                day: 'numeric'
                                                            })}
                                                        </p>
                                                        {day.value > 0 ? (
                                                            <>
                                                                <p className="text-amber-300">{formatCurrency(day.value, heatmapData.currency)}</p>
                                                                <p className="opacity-70">{day.count} transaction{day.count !== 1 ? 's' : ''}</p>
                                                            </>
                                                        ) : (
                                                            <p className="opacity-70">No expenses</p>
                                                        )}
                                                    </TooltipContent>
                                                </Tooltip>
                                            ) : (
                                                <div className="size-full" />
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ))}
                        </div>

                        {/* Summary */}
                        {heatmapData.max > 0 && (
                            <div className="pt-2 text-xs text-muted-foreground text-center">
                                Peak day: {formatCurrency(heatmapData.max, heatmapData.currency)}
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
