import { useState, useMemo } from 'react'
import ReactECharts from 'echarts-for-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useNetWorthHistory } from '@/hooks'
import type { ReportFilters } from '../types'
import type { CashFlowGroupBy } from '@/api/reports'

interface NetWorthChartProps {
    filters: ReportFilters
}

export function NetWorthChart({ filters }: NetWorthChartProps) {
    const [groupBy, setGroupBy] = useState<CashFlowGroupBy>('day')
    const { data, isLoading } = useNetWorthHistory(filters, groupBy)

    const currency = data?.currency || '$'

    const chartOption = useMemo(() => {
        if (!data) return {}

        return {
            tooltip: {
                trigger: 'axis',
                formatter: (params: { value: number; axisValue: string }[]) => {
                    const p = params[0]
                    return `<div class="font-medium mb-1">${p.axisValue}</div>
                        <div>Net Worth: <strong>${currency}${p.value.toLocaleString()}</strong></div>`
                },
            },
            grid: {
                left: 70,
                right: 20,
                top: 20,
                bottom: 30,
            },
            xAxis: {
                type: 'category',
                data: data.labels,
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
            series: [{
                name: 'Net Worth',
                type: 'line',
                data: data.values,
                smooth: true,
                symbol: 'circle',
                symbolSize: 8,
                lineStyle: {
                    color: '#3b82f6',
                    width: 3,
                },
                itemStyle: {
                    color: '#3b82f6',
                    borderColor: '#fff',
                    borderWidth: 2,
                },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [
                            { offset: 0, color: 'rgba(59, 130, 246, 0.25)' },
                            { offset: 1, color: 'rgba(59, 130, 246, 0)' },
                        ],
                    },
                },
            }],
        }
    }, [data, groupBy, currency])

    return (
        <Card>
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <div>
                        <CardTitle className="text-lg">Net Worth Over Time</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Track your wealth growth
                        </p>
                    </div>
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
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-[350px]" />
                ) : !data?.values?.length ? (
                    <div className="h-[350px] flex items-center justify-center text-muted-foreground">
                        No data for selected period
                    </div>
                ) : (
                    <ReactECharts
                        option={chartOption}
                        style={{ height: 350 }}
                        key={groupBy}
                    />
                )}
            </CardContent>
        </Card>
    )
}
