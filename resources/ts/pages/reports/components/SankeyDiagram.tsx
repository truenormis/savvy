import { useMemo, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import ReactECharts from 'echarts-for-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useMoneyFlow } from '@/hooks'
import type { ReportFilters } from '../types'

interface SankeyDiagramProps {
    filters: ReportFilters
}

export function SankeyDiagram({ filters }: SankeyDiagramProps) {
    const navigate = useNavigate()
    const { data, isLoading, error } = useMoneyFlow(filters)

    const sankeyOption = useMemo(() => {
        if (!data || data.nodes.length === 0) return null

        return {
            tooltip: {
                trigger: 'item',
                triggerOn: 'mousemove',
                formatter: (params: { data: { source?: string; target?: string; value: number }; name?: string; value?: number }) => {
                    if (params.data.source && params.data.target) {
                        // Link tooltip
                        return `${params.data.source} → ${params.data.target}<br/><strong>${data.currency}${params.data.value.toLocaleString()}</strong>`
                    }
                    // Node tooltip - params.value contains the calculated sum from ECharts
                    const nodeValue = params.value ?? params.data.value ?? 0
                    return `${params.name}: <strong>${data.currency}${nodeValue.toLocaleString()}</strong>`
                },
            },
            series: [{
                type: 'sankey',
                layout: 'none',
                emphasis: {
                    focus: 'adjacency',
                },
                nodeAlign: 'justify',
                lineStyle: {
                    color: 'gradient',
                    curveness: 0.5,
                },
                nodeGap: 12,
                nodeWidth: 20,
                label: {
                    fontSize: 12,
                },
                data: data.nodes,
                links: data.links,
            }],
        }
    }, [data])

    const handleSankeyClick = useCallback((params: { data: { source?: string; target?: string } }) => {
        if (params.data.source && params.data.target) {
            const searchParams = new URLSearchParams()
            searchParams.set('search', params.data.target)
            navigate(`/transactions?${searchParams.toString()}`)
        }
    }, [navigate])

    if (error) {
        return (
            <Card>
                <CardContent className="py-8 text-center text-red-500">
                    Failed to load money flow data
                </CardContent>
            </Card>
        )
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-lg">Money Flow</CardTitle>
                <p className="text-sm text-muted-foreground">
                    Where your money comes from and where it goes
                </p>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-[400px]" />
                ) : !sankeyOption ? (
                    <div className="h-[400px] flex items-center justify-center text-muted-foreground">
                        No data for selected period
                    </div>
                ) : (
                    <ReactECharts
                        option={sankeyOption}
                        style={{ height: 400 }}
                        onEvents={{
                            click: handleSankeyClick,
                        }}
                    />
                )}
            </CardContent>
        </Card>
    )
}
