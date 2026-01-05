import { useMemo } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover'
import { Checkbox } from '@/components/ui/checkbox'
import { useCategories, useAccounts, useTags } from '@/hooks'
import { RotateCcw, ChevronDown, Calendar, Filter } from 'lucide-react'
import type { ReportFilters, PeriodType, CompareType } from '../types'
import { getMonthOptions, getQuarterOptions, getYearOptions } from '../utils'

interface FiltersBarProps {
    filters: ReportFilters
    onFilterChange: <K extends keyof ReportFilters>(key: K, value: ReportFilters[K]) => void
    onToggleArrayFilter: (key: 'accountIds' | 'categoryIds' | 'tagIds', id: number) => void
    onReset: () => void
}

export function FiltersBar({ filters, onFilterChange, onToggleArrayFilter, onReset }: FiltersBarProps) {
    const { data: categories } = useCategories()
    const { data: accounts } = useAccounts()
    const { data: tags } = useTags()

    const monthOptions = useMemo(() => getMonthOptions(), [])
    const quarterOptions = useMemo(() => getQuarterOptions(), [])
    const yearOptions = useMemo(() => getYearOptions(), [])

    const hasActiveFilters =
        filters.accountIds.length > 0 ||
        filters.categoryIds.length > 0 ||
        filters.tagIds.length > 0

    return (
        <Card className="mb-6">
            <CardContent className="py-4">
                <div className="flex flex-wrap items-center gap-3">
                    {/* Period Type & Selection */}
                    <div className="flex items-center gap-2">
                        <Calendar className="size-4 text-muted-foreground" />

                        {/* Quick Presets */}
                        <div className="flex gap-1">
                            {(['month', 'quarter', 'year', 'ytd'] as PeriodType[]).map(type => (
                                <Badge
                                    key={type}
                                    variant={filters.periodType === type ? 'default' : 'outline'}
                                    className="cursor-pointer"
                                    onClick={() => onFilterChange('periodType', type)}
                                >
                                    {type === 'month' && 'Month'}
                                    {type === 'quarter' && 'Quarter'}
                                    {type === 'year' && 'Year'}
                                    {type === 'ytd' && 'YTD'}
                                </Badge>
                            ))}
                            <Badge
                                variant={filters.periodType === 'custom' ? 'default' : 'outline'}
                                className="cursor-pointer"
                                onClick={() => onFilterChange('periodType', 'custom')}
                            >
                                Custom
                            </Badge>
                        </div>

                        {/* Period Selector based on type */}
                        {filters.periodType === 'month' && (
                            <Select
                                value={filters.selectedMonth}
                                onValueChange={(val) => onFilterChange('selectedMonth', val)}
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {monthOptions.map(opt => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        {filters.periodType === 'quarter' && (
                            <Select
                                value={filters.selectedQuarter}
                                onValueChange={(val) => onFilterChange('selectedQuarter', val)}
                            >
                                <SelectTrigger className="w-[140px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {quarterOptions.map(opt => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        {filters.periodType === 'year' && (
                            <Select
                                value={filters.selectedYear}
                                onValueChange={(val) => onFilterChange('selectedYear', val)}
                            >
                                <SelectTrigger className="w-[100px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {yearOptions.map(opt => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        {filters.periodType === 'custom' && (
                            <div className="flex items-center gap-2">
                                <Input
                                    type="date"
                                    value={filters.customStartDate}
                                    onChange={(e) => onFilterChange('customStartDate', e.target.value)}
                                    className="w-[140px]"
                                />
                                <span className="text-muted-foreground">—</span>
                                <Input
                                    type="date"
                                    value={filters.customEndDate}
                                    onChange={(e) => onFilterChange('customEndDate', e.target.value)}
                                    className="w-[140px]"
                                />
                            </div>
                        )}
                    </div>

                    <div className="h-6 w-px bg-border" />

                    {/* Comparison Period */}
                    <Select
                        value={filters.compareWith}
                        onValueChange={(val) => onFilterChange('compareWith', val as CompareType)}
                    >
                        <SelectTrigger className="w-[200px]">
                            <SelectValue placeholder="Compare with..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No comparison</SelectItem>
                            <SelectItem value="previous_period">Previous period</SelectItem>
                            <SelectItem value="same_period_last_year">Same period last year</SelectItem>
                        </SelectContent>
                    </Select>

                    <div className="h-6 w-px bg-border" />

                    {/* Filter Dropdowns */}
                    <div className="flex items-center gap-2">
                        <Filter className="size-4 text-muted-foreground" />

                        {/* Accounts Filter */}
                        <Popover>
                            <PopoverTrigger asChild>
                                <Button variant="outline" size="sm" className="h-8">
                                    Accounts
                                    {filters.accountIds.length > 0 && (
                                        <Badge variant="secondary" className="ml-1 px-1.5">
                                            {filters.accountIds.length}
                                        </Badge>
                                    )}
                                    <ChevronDown className="ml-1 size-3" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-56 p-2" align="start">
                                <div className="space-y-1 max-h-64 overflow-y-auto">
                                    {accounts?.map(account => (
                                        <div
                                            key={account.id}
                                            className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-muted cursor-pointer"
                                            onClick={() => onToggleArrayFilter('accountIds', account.id)}
                                        >
                                            <Checkbox
                                                checked={filters.accountIds.includes(account.id)}
                                                className="pointer-events-none"
                                            />
                                            <span className="text-sm">{account.name}</span>
                                        </div>
                                    ))}
                                </div>
                            </PopoverContent>
                        </Popover>

                        {/* Categories Filter */}
                        <Popover>
                            <PopoverTrigger asChild>
                                <Button variant="outline" size="sm" className="h-8">
                                    Categories
                                    {filters.categoryIds.length > 0 && (
                                        <Badge variant="secondary" className="ml-1 px-1.5">
                                            {filters.categoryIds.length}
                                        </Badge>
                                    )}
                                    <ChevronDown className="ml-1 size-3" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-64 p-2" align="start">
                                <div className="space-y-1 max-h-64 overflow-y-auto">
                                    {categories?.map(category => (
                                        <div
                                            key={category.id}
                                            className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-muted cursor-pointer"
                                            onClick={() => onToggleArrayFilter('categoryIds', category.id)}
                                        >
                                            <Checkbox
                                                checked={filters.categoryIds.includes(category.id)}
                                                className="pointer-events-none"
                                            />
                                            <span className="text-sm">{category.name}</span>
                                        </div>
                                    ))}
                                </div>
                            </PopoverContent>
                        </Popover>

                        {/* Tags Filter */}
                        {tags && tags.length > 0 && (
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button variant="outline" size="sm" className="h-8">
                                        Tags
                                        {filters.tagIds.length > 0 && (
                                            <Badge variant="secondary" className="ml-1 px-1.5">
                                                {filters.tagIds.length}
                                            </Badge>
                                        )}
                                        <ChevronDown className="ml-1 size-3" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-48 p-2" align="start">
                                    <div className="space-y-1 max-h-64 overflow-y-auto">
                                        {tags.map(tag => (
                                            <div
                                                key={tag.id}
                                                className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-muted cursor-pointer"
                                                onClick={() => onToggleArrayFilter('tagIds', tag.id)}
                                            >
                                                <Checkbox
                                                    checked={filters.tagIds.includes(tag.id)}
                                                    className="pointer-events-none"
                                                />
                                                <span className="text-sm">#{tag.name}</span>
                                            </div>
                                        ))}
                                    </div>
                                </PopoverContent>
                            </Popover>
                        )}

                    </div>

                    {/* Reset Button */}
                    {hasActiveFilters && (
                        <>
                            <div className="h-6 w-px bg-border" />
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={onReset}
                                className="h-8 text-muted-foreground"
                            >
                                <RotateCcw className="size-3 mr-1" />
                                Reset
                            </Button>
                        </>
                    )}
                </div>
            </CardContent>
        </Card>
    )
}
