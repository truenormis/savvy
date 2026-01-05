import { useState, useMemo, useEffect } from 'react'
import ReactECharts from 'echarts-for-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover'
import { Button } from '@/components/ui/button'
import { LineChart, BarChart3, ChevronDown } from 'lucide-react'
import { useTransactionReportDynamics } from '@/hooks'
import type { ReportFilters } from '../types'
import type { CashFlowGroupBy } from '@/api/reports'

type ChartType = 'line' | 'bar'

interface SourceConfig {
    id: number
    name: string
    color: string
    enabled: boolean
}

interface IncomeDynamicsChartProps {
    filters: ReportFilters
}

export function IncomeDynamicsChart({ filters }: IncomeDynamicsChartProps) {
    const [chartType, setChartType] = useState<ChartType>('line')
    const [groupBy, setGroupBy] = useState<CashFlowGroupBy>('day')
    const [sources, setSources] = useState<SourceConfig[]>([])

    const { data, isLoading } = useTransactionReportDynamics(filters, 'income', groupBy)

    // Initialize sources from API data
    useEffect(() => {
        if (data?.datasets) {
            setSources(data.datasets.map((d, index) => ({
                id: d.id,
                name: d.name,
                color: d.color,
                enabled: index === 0, // Enable only "Total" by default
            })))
        }
    }, [data?.datasets])

    const toggleSource = (id: number) => {
        setSources(srcs => srcs.map(src =>
            src.id === id ? { ...src, enabled: !src.enabled } : src
        ))
    }

    const enabledSources = sources.filter(s => s.enabled)
    const enabledCount = enabledSources.length

    const currency = data?.currency || '$'

    const chartData = useMemo(() => {
        if (!data) return { labels: [], datasets: [] }

        return {
            labels: data.labels,
            datasets: data.datasets
                .filter(d => enabledSources.some(s => s.id === d.id))
                .map(d => ({
                    id: d.id,
                    name: d.name,
                    color: d.color,
                    data: d.data,
                })),
        }
    }, [data, enabledSources])

    const chartOption = useMemo(() => {
        const series = chartData.datasets.map(dataset => ({
            name: dataset.name,
            type: chartType,
            data: dataset.data,
            smooth: chartType === 'line',
            symbol: chartType === 'line' ? 'circle' : undefined,
            symbolSize: chartType === 'line' ? 6 : undefined,
            lineStyle: chartType === 'line' ? {
                color: dataset.color,
                width: 2,
            } : undefined,
            itemStyle: {
                color: dataset.color,
                borderRadius: chartType === 'bar' ? [4, 4, 0, 0] : undefined,
            },
            areaStyle: chartType === 'line' && chartData.datasets.length === 1 ? {
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 0,
                    y2: 1,
                    colorStops: [
                        { offset: 0, color: `${dataset.color}30` },
                        { offset: 1, color: `${dataset.color}05` },
                    ],
                },
            } : undefined,
            barMaxWidth: 20,
        }))

        return {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: chartType === 'bar' ? 'shadow' : 'cross',
                },
                formatter: (params: { seriesName: string; value: number; color: string; axisValue: string }[]) => {
                    const label = params[0]?.axisValue || ''
                    let html = `<div class="font-medium mb-2">${label}</div>`
                    params.forEach(p => {
                        html += `<div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" style="background:${p.color}"></span>
                            <span>${p.seriesName}: <strong>${currency}${p.value.toLocaleString()}</strong></span>
                        </div>`
                    })
                    return html
                },
            },
            legend: chartData.datasets.length > 1 ? {
                data: chartData.datasets.map(d => d.name),
                bottom: 0,
                textStyle: {
                    fontSize: 12,
                    color: '#64748b',
                },
            } : undefined,
            grid: {
                left: 60,
                right: 20,
                top: 20,
                bottom: chartData.datasets.length > 1 ? 50 : 30,
            },
            xAxis: {
                type: 'category',
                data: chartData.labels,
                axisLabel: {
                    fontSize: 11,
                    color: '#64748b',
                    rotate: groupBy === 'day' ? 45 : 0,
                    interval: groupBy === 'day' ? 4 : 0,
                },
                axisLine: {
                    lineStyle: { color: '#e2e8f0' },
                },
                axisTick: { show: false },
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    formatter: (val: number) => {
                        if (val >= 1000) return `${currency}${(val / 1000).toFixed(0)}k`
                        return `${currency}${val}`
                    },
                    fontSize: 11,
                    color: '#64748b',
                },
                splitLine: {
                    lineStyle: { color: '#f1f5f9', type: 'dashed' },
                },
            },
            series,
        }
    }, [chartData, chartType, groupBy, currency])

    return (
        <Card>
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <div>
                        <CardTitle className="text-lg">Income Dynamics</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            How your income changes over time
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {/* Chart type toggle */}
                        <div className="flex gap-1">
                            <Badge
                                variant={chartType === 'line' ? 'default' : 'outline'}
                                className="cursor-pointer gap-1.5"
                                onClick={() => setChartType('line')}
                            >
                                <LineChart className="size-3.5" />
                                Line
                            </Badge>
                            <Badge
                                variant={chartType === 'bar' ? 'default' : 'outline'}
                                className="cursor-pointer gap-1.5"
                                onClick={() => setChartType('bar')}
                            >
                                <BarChart3 className="size-3.5" />
                                Bar
                            </Badge>
                        </div>

                        {/* Grouping toggle */}
                        <div className="flex gap-1">
                            {(['day', 'week', 'month'] as CashFlowGroupBy[]).map(g => (
                                <Badge
                                    key={g}
                                    variant={groupBy === g ? 'default' : 'outline'}
                                    className="cursor-pointer capitalize"
                                    onClick={() => setGroupBy(g)}
                                >
                                    {g}
                                </Badge>
                            ))}
                        </div>

                        {/* Source selector */}
                        <Popover>
                            <PopoverTrigger asChild>
                                <Button variant="outline" size="sm" className="h-7 gap-1">
                                    Sources
                                    <Badge variant="secondary" className="ml-1 px-1.5 text-xs">
                                        {enabledCount}
                                    </Badge>
                                    <ChevronDown className="size-3" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-56 p-2" align="end">
                                <div className="space-y-1">
                                    {sources.map(src => (
                                        <div
                                            key={src.id}
                                            className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-muted cursor-pointer"
                                            onClick={() => toggleSource(src.id)}
                                        >
                                            <Checkbox
                                                checked={src.enabled}
                                                className="pointer-events-none"
                                            />
                                            <span
                                                className="w-2.5 h-2.5 rounded-full"
                                                style={{ backgroundColor: src.color }}
                                            />
                                            <Label className="text-sm cursor-pointer flex-1">
                                                {src.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-[350px]" />
                ) : chartData.labels.length === 0 ? (
                    <div className="h-[350px] flex items-center justify-center text-muted-foreground">
                        No data for selected period
                    </div>
                ) : (
                    <ReactECharts
                        option={chartOption}
                        style={{ height: 350 }}
                        key={`${chartType}-${groupBy}`}
                    />
                )}
            </CardContent>
        </Card>
    )
}
