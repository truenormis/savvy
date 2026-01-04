import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, ArrowDownLeft, ArrowUpRight, ArrowLeftRight, Filter, ArrowUpDown, X } from 'lucide-react'
import { Row } from '@tanstack/react-table'
import { Page, PageHeader, DataTable } from '@/components/shared'
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
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { createTransactionColumns } from '@/components/features/transactions'
import { useTransactions, useDeleteTransaction, useDuplicateTransaction, useCategories, useTags } from '@/hooks'
import { TransactionFilters, TransactionType, Transaction } from '@/types'
import { cn } from '@/lib/utils'

const TYPE_FILTERS: { value: TransactionType | undefined; label: string; icon?: typeof ArrowDownLeft }[] = [
    { value: undefined, label: 'All' },
    { value: 'income', label: 'Income', icon: ArrowDownLeft },
    { value: 'expense', label: 'Expense', icon: ArrowUpRight },
    { value: 'transfer', label: 'Transfer', icon: ArrowLeftRight },
]

function TransactionItems({ row }: { row: Row<Transaction> }) {
    const items = row.original.items
    if (!items || items.length === 0) return null

    return (
        <div className="px-4 py-3 ml-10">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-muted-foreground text-xs">
                        <th className="text-left font-medium pb-2">Item</th>
                        <th className="text-right font-medium pb-2 w-20">Qty</th>
                        <th className="text-right font-medium pb-2 w-24">Price</th>
                        <th className="text-right font-medium pb-2 w-24">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((item, idx) => (
                        <tr key={item.id ?? idx} className="border-t border-border/50">
                            <td className="py-1.5">{item.name}</td>
                            <td className="py-1.5 text-right font-mono">{item.quantity}</td>
                            <td className="py-1.5 text-right font-mono">{item.pricePerUnit.toFixed(2)}</td>
                            <td className="py-1.5 text-right font-mono font-medium">{item.totalPrice.toFixed(2)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    )
}

const SORT_OPTIONS = [
    { value: 'date:desc', label: 'Date (Newest)' },
    { value: 'date:asc', label: 'Date (Oldest)' },
    { value: 'amount:desc', label: 'Amount (High to Low)' },
    { value: 'amount:asc', label: 'Amount (Low to High)' },
]

export default function TransactionsPage() {
    const [filters, setFilters] = useState<TransactionFilters & { with_summary?: boolean }>({
        per_page: 20,
        page: 1,
        with_summary: true,
        sort_by: 'date',
        sort_direction: 'desc',
    })
    const [filtersOpen, setFiltersOpen] = useState(false)

    const { data, isLoading } = useTransactions(filters)
    const deleteTransaction = useDeleteTransaction()
    const duplicateTransaction = useDuplicateTransaction()
    const { data: categories } = useCategories()
    const { data: tags } = useTags()

    const columns = createTransactionColumns(
        (id) => deleteTransaction.mutate(id),
        (id) => duplicateTransaction.mutate(id)
    )

    const transactions = data?.data ?? []
    const summary = data?.summary
    const meta = data?.meta

    const activeFiltersCount = [
        filters.category_ids?.length,
        filters.tag_ids?.length,
        filters.start_date,
        filters.end_date,
    ].filter(Boolean).length

    const clearFilters = () => {
        setFilters(f => ({
            ...f,
            category_ids: undefined,
            tag_ids: undefined,
            start_date: undefined,
            end_date: undefined,
            page: 1,
        }))
    }

    const toggleCategory = (id: number) => {
        setFilters(f => {
            const current = f.category_ids ?? []
            const newIds = current.includes(id)
                ? current.filter(c => c !== id)
                : [...current, id]
            return { ...f, category_ids: newIds.length ? newIds : undefined, page: 1 }
        })
    }

    const toggleTag = (id: number) => {
        setFilters(f => {
            const current = f.tag_ids ?? []
            const newIds = current.includes(id)
                ? current.filter(t => t !== id)
                : [...current, id]
            return { ...f, tag_ids: newIds.length ? newIds : undefined, page: 1 }
        })
    }

    // Filter categories based on selected type
    const filteredCategories = categories?.filter(c =>
        !filters.type || filters.type === 'transfer' || c.type === filters.type
    ) ?? []

    return (
        <Page title="Transactions">
            <PageHeader
                title="Transactions"
                description="Track your income, expenses and transfers"
                createLink="/transactions/create"
                createLabel="New Transaction"
            />

            {/* Summary Cards */}
            {summary && (
                <div className="grid grid-cols-3 gap-4 mb-6">
                    <Card>
                        <CardContent className="pt-4">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                                <ArrowDownLeft className="size-4 text-green-600" />
                                Income
                            </div>
                            <div className="text-2xl font-bold text-green-600">
                                +{(Number(summary.income) || 0).toFixed(2)} {summary.currency}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-4">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                                <ArrowUpRight className="size-4 text-red-600" />
                                Expenses
                            </div>
                            <div className="text-2xl font-bold text-red-600">
                                -{(Number(summary.expense) || 0).toFixed(2)} {summary.currency}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-4">
                            <div className="text-sm text-muted-foreground mb-1">
                                Balance
                            </div>
                            <div className={cn(
                                'text-2xl font-bold',
                                (Number(summary.balance) || 0) >= 0 ? 'text-green-600' : 'text-red-600'
                            )}>
                                {(Number(summary.balance) || 0) >= 0 ? '+' : ''}{(Number(summary.balance) || 0).toFixed(2)} {summary.currency}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Type Filter & Sort */}
            <div className="flex items-center justify-between gap-4 mb-4">
                <div className="flex gap-2">
                    {TYPE_FILTERS.map(({ value, label, icon: Icon }) => (
                        <Button
                            key={label}
                            variant={filters.type === value ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setFilters(f => ({ ...f, type: value, page: 1 }))}
                        >
                            {Icon && <Icon className="size-4 mr-1" />}
                            {label}
                        </Button>
                    ))}
                </div>
                <div className="flex items-center gap-2">
                    <Select
                        value={`${filters.sort_by}:${filters.sort_direction}`}
                        onValueChange={(val) => {
                            const [sort_by, sort_direction] = val.split(':') as ['date' | 'amount', 'asc' | 'desc']
                            setFilters(f => ({ ...f, sort_by, sort_direction, page: 1 }))
                        }}
                    >
                        <SelectTrigger className="w-[180px] h-9">
                            <ArrowUpDown className="size-4 mr-2" />
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {SORT_OPTIONS.map(opt => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {/* Advanced Filters */}
            <Collapsible open={filtersOpen} onOpenChange={setFiltersOpen} className="mb-4">
                <div className="flex items-center gap-2">
                    <CollapsibleTrigger asChild>
                        <Button variant="outline" size="sm">
                            <Filter className="size-4 mr-2" />
                            Filters
                            {activeFiltersCount > 0 && (
                                <Badge variant="secondary" className="ml-2 px-1.5 py-0 text-xs">
                                    {activeFiltersCount}
                                </Badge>
                            )}
                        </Button>
                    </CollapsibleTrigger>
                    {activeFiltersCount > 0 && (
                        <Button variant="ghost" size="sm" onClick={clearFilters}>
                            <X className="size-4 mr-1" />
                            Clear
                        </Button>
                    )}
                </div>
                <CollapsibleContent className="mt-4 space-y-4">
                    <Card>
                        <CardContent className="pt-4 space-y-4">
                            {/* Date Range */}
                            <div>
                                <label className="text-sm font-medium mb-2 block">Date Range</label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        type="date"
                                        value={filters.start_date ?? ''}
                                        onChange={(e) => setFilters(f => ({
                                            ...f,
                                            start_date: e.target.value || undefined,
                                            page: 1
                                        }))}
                                        className="w-auto"
                                    />
                                    <span className="text-muted-foreground">to</span>
                                    <Input
                                        type="date"
                                        value={filters.end_date ?? ''}
                                        onChange={(e) => setFilters(f => ({
                                            ...f,
                                            end_date: e.target.value || undefined,
                                            page: 1
                                        }))}
                                        className="w-auto"
                                    />
                                </div>
                            </div>

                            {/* Categories */}
                            {filteredCategories.length > 0 && (
                                <div>
                                    <label className="text-sm font-medium mb-2 block">Categories</label>
                                    <div className="flex flex-wrap gap-2">
                                        {filteredCategories.map((category) => {
                                            const isSelected = filters.category_ids?.includes(category.id)
                                            return (
                                                <Badge
                                                    key={category.id}
                                                    variant={isSelected ? 'default' : 'outline'}
                                                    className={cn(
                                                        'cursor-pointer transition-colors',
                                                        isSelected ? 'hover:bg-primary/80' : 'hover:bg-muted'
                                                    )}
                                                    onClick={() => toggleCategory(category.id)}
                                                >
                                                    {category.icon} {category.name}
                                                </Badge>
                                            )
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* Tags */}
                            {tags && tags.length > 0 && (
                                <div>
                                    <label className="text-sm font-medium mb-2 block">Tags</label>
                                    <div className="flex flex-wrap gap-2">
                                        {tags.map((tag) => {
                                            const isSelected = filters.tag_ids?.includes(tag.id)
                                            return (
                                                <Badge
                                                    key={tag.id}
                                                    variant={isSelected ? 'default' : 'outline'}
                                                    className={cn(
                                                        'cursor-pointer transition-colors',
                                                        isSelected ? 'hover:bg-primary/80' : 'hover:bg-muted'
                                                    )}
                                                    onClick={() => toggleTag(tag.id)}
                                                >
                                                    #{tag.name}
                                                </Badge>
                                            )
                                        })}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </CollapsibleContent>
            </Collapsible>

            <DataTable
                data={transactions}
                columns={columns}
                isLoading={isLoading}
                emptyTitle="No transactions found"
                emptyDescription="Create your first transaction to start tracking"
                emptyAction={
                    <Button asChild>
                        <Link to="/transactions/create">
                            <Plus className="size-4" />
                            New Transaction
                        </Link>
                    </Button>
                }
                renderSubComponent={TransactionItems}
                getRowCanExpand={(row) => (row.original.itemsCount ?? row.original.items?.length ?? 0) > 1}
            />

            {/* Pagination */}
            {meta && meta.last_page > 1 && (
                <div className="flex items-center justify-between mt-4">
                    <div className="text-sm text-muted-foreground">
                        Showing {meta.from} to {meta.to} of {meta.total} transactions
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setFilters(f => ({ ...f, page: (f.page ?? 1) - 1 }))}
                            disabled={meta.current_page === 1}
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setFilters(f => ({ ...f, page: (f.page ?? 1) + 1 }))}
                            disabled={meta.current_page === meta.last_page}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            )}
        </Page>
    )
}
