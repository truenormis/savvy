import { useLayoutEffect, useMemo, useRef, useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { useActivityHeatmap } from '@/hooks'
import type { ReportFilters } from '../types'

interface ActivityHeatmapProps {
    filters: ReportFilters
}

const MIN_GAP = 1

const COLOR_RAMP: Array<{ t: number; c: [number, number, number] }> = [
    { t: 0, c: [254, 243, 199] }, // amber-100
    { t: 0.35, c: [253, 224, 71] }, // amber-300
    { t: 0.65, c: [249, 115, 22] }, // orange-500
    { t: 1, c: [239, 68, 68] }, // red-500
]

const GRADIENT_CSS = 'linear-gradient(to right, rgb(254,243,199), rgb(253,224,71), rgb(249,115,22), rgb(239,68,68))'

function colorForValue(value: number, max: number): string | null {
    if (value <= 0 || max <= 0) return null

    const t = Math.min(1, Math.max(0, Math.pow(value / max, 0.6)))
    const lerp = (a: number, b: number, f: number) => Math.round(a + (b - a) * f)

    for (let i = 1; i < COLOR_RAMP.length; i++) {
        if (t <= COLOR_RAMP[i].t) {
            const a = COLOR_RAMP[i - 1]
            const b = COLOR_RAMP[i]
            const f = (t - a.t) / (b.t - a.t)
            return `rgb(${lerp(a.c[0], b.c[0], f)}, ${lerp(a.c[1], b.c[1], f)}, ${lerp(a.c[2], b.c[2], f)})`
        }
    }

    const last = COLOR_RAMP[COLOR_RAMP.length - 1].c
    return `rgb(${last[0]}, ${last[1]}, ${last[2]})`
}

export function ActivityHeatmap({ filters }: ActivityHeatmapProps) {
    const { data, isLoading, error } = useActivityHeatmap(filters)
    const areaRef = useRef<HTMLDivElement>(null)
    const [cols, setCols] = useState(7)

    const parseLocalDate = (dateStr: string) => {
        const [year, month, day] = dateStr.split('-').map(Number)
        return new Date(year, month - 1, day)
    }

    const heatmapData = useMemo(() => {
        if (!data?.items?.length) return null

        return {
            cells: data.items.map(item => ({ value: item.value, count: item.count, date: item.date })),
            max: data.max,
            currency: data.currency,
        }
    }, [data])

    const count = heatmapData?.cells.length ?? 0

    useLayoutEffect(() => {
        const el = areaRef.current
        if (!el || count === 0) return

        const compute = () => {
            const w = el.clientWidth
            const h = el.clientHeight
            if (!w || !h) return

            // Pick the column count that makes each filled cell closest to square
            let bestRatio = Infinity
            let bestCols = 1
            for (let c = 1; c <= count; c++) {
                const rows = Math.ceil(count / c)
                const cellW = w / c
                const cellH = h / rows
                const ratio = Math.max(cellW, cellH) / Math.min(cellW, cellH)
                if (ratio < bestRatio) {
                    bestRatio = ratio
                    bestCols = c
                }
            }

            setCols(bestCols)
        }

        compute()
        const ro = new ResizeObserver(compute)
        ro.observe(el)
        return () => ro.disconnect()
    }, [count])

    const formatCurrency = (val: number, currency: string) => {
        return `${currency}${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
    }

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
        <Card className="flex flex-col">
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
                            <div className="h-2.5 w-20 rounded-sm" style={{ background: GRADIENT_CSS }} />
                            <span>More</span>
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent className="flex-1 flex flex-col min-h-0">
                {isLoading ? (
                    <Skeleton className="flex-1 min-h-[220px]" />
                ) : !heatmapData ? (
                    <div className="flex-1 min-h-[220px] flex items-center justify-center text-muted-foreground">
                        No data for selected period
                    </div>
                ) : (
                    <div ref={areaRef} className="flex-1 min-h-[220px] overflow-hidden">
                        <div
                            className="grid size-full"
                            style={{
                                gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))`,
                                gridAutoRows: 'minmax(0, 1fr)',
                                gap: MIN_GAP,
                            }}
                        >
                            {heatmapData.cells.map((cell, i) => {
                                const color = colorForValue(cell.value, heatmapData.max)
                                return (
                                <Tooltip key={i}>
                                    <TooltipTrigger asChild>
                                        <div
                                            style={{ backgroundColor: color ?? undefined }}
                                            className={cn(
                                                'size-full rounded-[2px] cursor-default transition-colors',
                                                'hover:ring-2 hover:ring-ring hover:ring-offset-1 hover:z-10',
                                                color ? '' : 'bg-muted',
                                            )}
                                        />
                                    </TooltipTrigger>
                                    <TooltipContent side="top" className="text-center">
                                        <p className="font-medium">
                                            {parseLocalDate(cell.date).toLocaleDateString('en-US', {
                                                weekday: 'short',
                                                month: 'short',
                                                day: 'numeric',
                                                year: 'numeric',
                                            })}
                                        </p>
                                        {cell.value > 0 ? (
                                            <>
                                                <p className="text-amber-300">{formatCurrency(cell.value, heatmapData.currency)}</p>
                                                <p className="opacity-70">{cell.count} transaction{cell.count !== 1 ? 's' : ''}</p>
                                            </>
                                        ) : (
                                            <p className="opacity-70">No expenses</p>
                                        )}
                                    </TooltipContent>
                                </Tooltip>
                                )
                            })}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
