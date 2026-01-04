import { useState, useMemo } from 'react'
import { Page, PageHeader } from '@/components/shared'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Progress } from '@/components/ui/progress'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Checkbox } from '@/components/ui/checkbox'
import { useReportOptions, useGenerateReport, useCategories, useAccounts, useTags } from '@/hooks'
import { ReportConfig, ReportType, ReportGroupBy, ReportMetric, ReportCompareWith, ReportResult } from '@/types'
import { cn } from '@/lib/utils'
import { BarChart3, TrendingUp, TrendingDown, Loader2, ChevronRight, ChevronDown } from 'lucide-react'

const DEFAULT_CONFIG: ReportConfig = {
    type: 'expenses',
    start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
    group_by: 'categories',
    metrics: ['sum', 'count'],
}

export default function ReportsPage() {
    const [config, setConfig] = useState<ReportConfig>(DEFAULT_CONFIG)
    const [report, setReport] = useState<ReportResult | null>(null)
    const [expandedRows, setExpandedRows] = useState<Set<string | number>>(new Set())

    const { data: options, isLoading: optionsLoading } = useReportOptions()
    const { data: categories } = useCategories()
    const { data: accounts } = useAccounts()
    const { data: tags } = useTags()
    const generateReport = useGenerateReport()

    const handleGenerate = async () => {
        const result = await generateReport.mutateAsync(config)
        setReport(result)
        setExpandedRows(new Set())
    }

    const handlePresetSelect = (preset: { start_date: string; end_date: string }) => {
        setConfig(c => ({ ...c, start_date: preset.start_date, end_date: preset.end_date }))
    }

    const toggleMetric = (metric: ReportMetric) => {
        setConfig(c => {
            const current = c.metrics ?? ['sum']
            const newMetrics = current.includes(metric)
                ? current.filter(m => m !== metric)
                : [...current, metric]
            return { ...c, metrics: newMetrics.length ? newMetrics : ['sum'] }
        })
    }

    const toggleFilter = (type: 'category_ids' | 'account_ids' | 'tag_ids', id: number) => {
        setConfig(c => {
            const current = c[type] ?? []
            const newIds = current.includes(id)
                ? current.filter(i => i !== id)
                : [...current, id]
            return { ...c, [type]: newIds.length ? newIds : undefined }
        })
    }

    const toggleExpand = (key: string | number) => {
        setExpandedRows(prev => {
            const next = new Set(prev)
            if (next.has(key)) {
                next.delete(key)
            } else {
                next.add(key)
            }
            return next
        })
    }

    const formatValue = (metric: string, value: number) => {
        if (metric === 'count') return value.toLocaleString()
        if (metric === 'percent_of_total' || metric === 'percent_of_income' || metric === 'percent') {
            return `${value.toFixed(1)}%`
        }
        return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    }

    const getMetricLabel = (metric: string) => {
        return options?.metrics.find(m => m.value === metric)?.label ?? metric
    }

    if (optionsLoading) {
        return (
            <Page title="Reports">
                <div className="flex items-center justify-center min-h-[400px]">
                    <Loader2 className="size-8 animate-spin text-muted-foreground" />
                </div>
            </Page>
        )
    }

    return (
        <Page title="Reports">
            <PageHeader
                title="Reports"
                description="Generate custom reports and analyze your finances"
            />

            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                {/* Configuration Panel */}
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle className="text-lg">Configuration</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Report Type */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Report Type</label>
                            <Select
                                value={config.type}
                                onValueChange={(val) => setConfig(c => ({ ...c, type: val as ReportType }))}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options?.types.map(t => (
                                        <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Period Presets */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Quick Period</label>
                            <div className="flex flex-wrap gap-1">
                                {options?.presets.slice(0, 6).map(preset => (
                                    <Badge
                                        key={preset.label}
                                        variant="outline"
                                        className="cursor-pointer hover:bg-muted text-xs"
                                        onClick={() => handlePresetSelect(preset)}
                                    >
                                        {preset.label}
                                    </Badge>
                                ))}
                            </div>
                        </div>

                        {/* Date Range */}
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <label className="text-sm font-medium mb-1 block">From</label>
                                <Input
                                    type="date"
                                    value={config.start_date}
                                    onChange={(e) => setConfig(c => ({ ...c, start_date: e.target.value }))}
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium mb-1 block">To</label>
                                <Input
                                    type="date"
                                    value={config.end_date}
                                    onChange={(e) => setConfig(c => ({ ...c, end_date: e.target.value }))}
                                />
                            </div>
                        </div>

                        {/* Group By */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Group By</label>
                            <Select
                                value={config.group_by ?? 'none'}
                                onValueChange={(val) => setConfig(c => ({ ...c, group_by: val as ReportGroupBy }))}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options?.group_by.map(g => (
                                        <SelectItem key={g.value} value={g.value}>{g.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Sub Group By */}
                        {config.group_by && config.group_by !== 'none' && (
                            <div>
                                <label className="text-sm font-medium mb-2 block">Then By (Optional)</label>
                                <Select
                                    value={config.sub_group_by ?? 'none'}
                                    onValueChange={(val) => setConfig(c => ({
                                        ...c,
                                        sub_group_by: val === 'none' ? undefined : val as ReportGroupBy
                                    }))}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="None" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        {options?.group_by
                                            .filter(g => g.value !== config.group_by && g.value !== 'none')
                                            .map(g => (
                                                <SelectItem key={g.value} value={g.value}>{g.label}</SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {/* Metrics */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Metrics</label>
                            <div className="space-y-2">
                                {options?.metrics.map(m => (
                                    <div key={m.value} className="flex items-center space-x-2">
                                        <Checkbox
                                            id={`metric-${m.value}`}
                                            checked={config.metrics?.includes(m.value as ReportMetric)}
                                            onCheckedChange={() => toggleMetric(m.value as ReportMetric)}
                                        />
                                        <label htmlFor={`metric-${m.value}`} className="text-sm cursor-pointer">
                                            {m.label}
                                        </label>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Compare With */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Compare With</label>
                            <Select
                                value={config.compare_with ?? 'none'}
                                onValueChange={(val) => setConfig(c => ({
                                    ...c,
                                    compare_with: val === 'none' ? undefined : val as ReportCompareWith
                                }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
                                    {options?.compare_with.map(c => (
                                        <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Filters */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Filter by Categories</label>
                            <div className="flex flex-wrap gap-1 max-h-32 overflow-y-auto">
                                {categories?.map(cat => (
                                    <Badge
                                        key={cat.id}
                                        variant={config.category_ids?.includes(cat.id) ? 'default' : 'outline'}
                                        className="cursor-pointer text-xs"
                                        onClick={() => toggleFilter('category_ids', cat.id)}
                                    >
                                        {cat.icon} {cat.name}
                                    </Badge>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium mb-2 block">Filter by Accounts</label>
                            <div className="flex flex-wrap gap-1 max-h-24 overflow-y-auto">
                                {accounts?.map(acc => (
                                    <Badge
                                        key={acc.id}
                                        variant={config.account_ids?.includes(acc.id) ? 'default' : 'outline'}
                                        className="cursor-pointer text-xs"
                                        onClick={() => toggleFilter('account_ids', acc.id)}
                                    >
                                        {acc.name}
                                    </Badge>
                                ))}
                            </div>
                        </div>

                        {tags && tags.length > 0 && (
                            <div>
                                <label className="text-sm font-medium mb-2 block">Filter by Tags</label>
                                <div className="flex flex-wrap gap-1">
                                    {tags.map(tag => (
                                        <Badge
                                            key={tag.id}
                                            variant={config.tag_ids?.includes(tag.id) ? 'default' : 'outline'}
                                            className="cursor-pointer text-xs"
                                            onClick={() => toggleFilter('tag_ids', tag.id)}
                                        >
                                            #{tag.name}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        <Button
                            onClick={handleGenerate}
                            className="w-full"
                            disabled={generateReport.isPending}
                        >
                            {generateReport.isPending ? (
                                <Loader2 className="size-4 animate-spin mr-2" />
                            ) : (
                                <BarChart3 className="size-4 mr-2" />
                            )}
                            Generate Report
                        </Button>
                    </CardContent>
                </Card>

                {/* Report Results */}
                <div className="lg:col-span-3 space-y-4">
                    {!report && !generateReport.isPending && (
                        <Card>
                            <CardContent className="py-12 text-center text-muted-foreground">
                                <BarChart3 className="size-12 mx-auto mb-4 opacity-50" />
                                <p>Configure your report and click "Generate Report" to see results</p>
                            </CardContent>
                        </Card>
                    )}

                    {generateReport.isPending && (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <Loader2 className="size-8 animate-spin mx-auto mb-4" />
                                <p className="text-muted-foreground">Generating report...</p>
                            </CardContent>
                        </Card>
                    )}

                    {report && (
                        <>
                            {/* Summary Cards */}
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                {Object.entries(report.totals ?? {}).filter(([_, v]) => v !== null).map(([key, value]) => (
                                    <Card key={key}>
                                        <CardContent className="pt-4">
                                            <div className="text-sm text-muted-foreground mb-1">
                                                {getMetricLabel(key)}
                                            </div>
                                            <div className="text-2xl font-bold">
                                                {formatValue(key, value as number)}
                                            </div>
                                            {report.comparison && report.comparison.totals && (
                                                <ComparisonIndicator
                                                    current={value as number}
                                                    previous={(report.comparison.totals[key] ?? 0) as number}
                                                    metric={key}
                                                />
                                            )}
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>

                            {/* Data Table */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">
                                        {options?.types.find(t => t.value === report.type)?.label} - {' '}
                                        {options?.group_by.find(g => g.value === report.group_by)?.label}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-8"></TableHead>
                                                <TableHead>Name</TableHead>
                                                {(report.metrics ?? []).map(metric => (
                                                    <TableHead key={metric} className="text-right">
                                                        {getMetricLabel(metric)}
                                                    </TableHead>
                                                ))}
                                                {report.comparison && (
                                                    <TableHead className="text-right">vs Previous</TableHead>
                                                )}
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {(report.data ?? []).map((item) => (
                                                <ReportRow
                                                    key={item.key}
                                                    item={item}
                                                    metrics={report.metrics ?? []}
                                                    comparison={report.comparison}
                                                    expandedRows={expandedRows}
                                                    toggleExpand={toggleExpand}
                                                    formatValue={formatValue}
                                                    level={0}
                                                />
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>

                            {/* Budget Comparison */}
                            {report.budget_comparison && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg">Budget Comparison</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            {report.budget_comparison.map((budget) => (
                                                <div key={budget.budget_id} className="space-y-2">
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-medium">{budget.budget_name}</span>
                                                        <span className={cn(
                                                            'text-sm',
                                                            budget.is_exceeded ? 'text-red-600' : 'text-green-600'
                                                        )}>
                                                            {budget.spent.toFixed(2)} / {budget.budget_amount.toFixed(2)}
                                                        </span>
                                                    </div>
                                                    <Progress
                                                        value={Math.min(budget.percent, 100)}
                                                        className={cn(
                                                            budget.is_exceeded && '[&>div]:bg-red-600'
                                                        )}
                                                    />
                                                    <div className="flex justify-between text-xs text-muted-foreground">
                                                        <span>{budget.percent.toFixed(1)}% used</span>
                                                        <span>
                                                            {budget.remaining >= 0
                                                                ? `${budget.remaining.toFixed(2)} remaining`
                                                                : `${Math.abs(budget.remaining).toFixed(2)} over budget`
                                                            }
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </>
                    )}
                </div>
            </div>
        </Page>
    )
}

function ReportRow({
    item,
    metrics,
    comparison,
    expandedRows,
    toggleExpand,
    formatValue,
    level,
}: {
    item: ReportResult['data'][0]
    metrics: ReportMetric[]
    comparison?: ReportResult['comparison']
    expandedRows: Set<string | number>
    toggleExpand: (key: string | number) => void
    formatValue: (metric: string, value: number) => string
    level: number
}) {
    const hasChildren = item.children && item.children.length > 0
    const isExpanded = expandedRows.has(item.key)

    const comparisonItem = comparison?.data?.find(d => d.key === item.key)

    return (
        <>
            <TableRow className={cn(level > 0 && 'bg-muted/30')}>
                <TableCell className="w-8">
                    {hasChildren && (
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => toggleExpand(item.key)}
                            className="size-6"
                        >
                            {isExpanded ? (
                                <ChevronDown className="size-4" />
                            ) : (
                                <ChevronRight className="size-4" />
                            )}
                        </Button>
                    )}
                </TableCell>
                <TableCell style={{ paddingLeft: `${level * 24 + 8}px` }}>
                    {item.label}
                </TableCell>
                {metrics.map(metric => (
                    <TableCell key={metric} className="text-right font-mono">
                        {formatValue(metric, item.metrics[metric] ?? 0)}
                    </TableCell>
                ))}
                {comparison && (
                    <TableCell className="text-right">
                        {comparisonItem && (
                            <ComparisonIndicator
                                current={item.metrics.sum ?? 0}
                                previous={comparisonItem.metrics.sum ?? 0}
                                metric="sum"
                            />
                        )}
                    </TableCell>
                )}
            </TableRow>
            {hasChildren && isExpanded && (item.children ?? []).map(child => (
                <ReportRow
                    key={child.key}
                    item={child}
                    metrics={metrics}
                    comparison={comparison}
                    expandedRows={expandedRows}
                    toggleExpand={toggleExpand}
                    formatValue={formatValue}
                    level={level + 1}
                />
            ))}
        </>
    )
}

function ComparisonIndicator({
    current,
    previous,
    metric
}: {
    current: number
    previous: number
    metric: string
}) {
    if (previous === 0) return null

    const change = ((current - previous) / previous) * 100
    const isPositive = change > 0
    const isExpense = metric === 'sum' // For expenses, lower is better

    return (
        <div className={cn(
            'flex items-center gap-1 text-xs',
            isExpense
                ? (isPositive ? 'text-red-600' : 'text-green-600')
                : (isPositive ? 'text-green-600' : 'text-red-600')
        )}>
            {isPositive ? (
                <TrendingUp className="size-3" />
            ) : (
                <TrendingDown className="size-3" />
            )}
            {Math.abs(change).toFixed(1)}%
        </div>
    )
}
