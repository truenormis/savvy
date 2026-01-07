import { useForm, useFieldArray, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useCallback, useEffect, useMemo, useRef, type KeyboardEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
    Form,
    FormField,
    FormItem,
    FormLabel,
    FormControl,
    FormMessage,
} from '@/components/ui/form'
import { transactionSchema, TransactionFormValues } from '@/schemas/transactions'
import { useAccounts, useCategories, useTags } from '@/hooks'
import { cn } from '@/lib/utils'
import { Plus, Trash2, ArrowDownLeft, ArrowUpRight, ArrowLeftRight } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { AccountSelect } from '@/components/shared/AccountSelect'
import { CategorySelect } from '@/components/shared/CategorySelect'
import { FormWrapper } from '@/components/shared/FormWrapper'

const TRANSACTION_TYPES = [
    { value: 'income', label: 'Income', icon: ArrowDownLeft, color: 'text-green-600' },
    { value: 'expense', label: 'Expense', icon: ArrowUpRight, color: 'text-red-600' },
    { value: 'transfer', label: 'Transfer', icon: ArrowLeftRight, color: 'text-blue-600' },
] as const

interface TransactionFormProps {
    defaultValues?: Partial<TransactionFormValues>
    onSubmit: (data: TransactionFormValues) => void
    onTypeChange?: (type: TransactionFormValues['type']) => void
    isSubmitting?: boolean
    submitLabel?: string
}

export function TransactionForm({
    defaultValues,
    onSubmit,
    onTypeChange,
    isSubmitting,
    submitLabel = 'Save',
}: TransactionFormProps) {
    const { data: accounts } = useAccounts({ active: true, exclude_debts: true })
    const { data: categories } = useCategories()
    const { data: tags } = useTags()

    const formDefaults = useMemo(() => {
        const today = new Date().toISOString().split('T')[0]
        return {
            type: defaultValues?.type ?? 'expense' as const,
            account_id: defaultValues?.account_id ?? 0,
            to_account_id: defaultValues?.to_account_id ?? null,
            category_id: defaultValues?.category_id ?? null,
            amount: defaultValues?.amount ?? 0,
            to_amount: defaultValues?.to_amount ?? null,
            description: defaultValues?.description ?? '',
            date: defaultValues?.date || today,
            items: defaultValues?.items ?? [],
            tag_ids: defaultValues?.tag_ids ?? [],
        }
    }, [defaultValues])

    const form = useForm<TransactionFormValues>({
        resolver: zodResolver(transactionSchema),
        defaultValues: formDefaults,
    })

    // Reset form when defaults change (e.g., when editing and data loads)
    useEffect(() => {
        form.reset(formDefaults)
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [formDefaults])

    const { fields, append, remove } = useFieldArray({
        control: form.control,
        name: 'items',
    })

    const transactionType = useWatch({ control: form.control, name: 'type' })
    const accountId = useWatch({ control: form.control, name: 'account_id' })
    const categoryId = useWatch({ control: form.control, name: 'category_id' })
    const items = useWatch({ control: form.control, name: 'items' })
    const amount = useWatch({ control: form.control, name: 'amount' })
    const toAccountId = useWatch({ control: form.control, name: 'to_account_id' })
    const toAmount = useWatch({ control: form.control, name: 'to_amount' })
    const selectedTagIds = useWatch({ control: form.control, name: 'tag_ids' }) ?? []

    // Filter categories based on transaction type and sort by popularity
    const filteredCategories = useMemo(() => {
        return (categories?.filter(c => c.type === transactionType) ?? [])
            .sort((a, b) => (b.transactionsCount ?? 0) - (a.transactionsCount ?? 0))
    }, [categories, transactionType])

    // Auto-select first account if none selected
    useEffect(() => {
        if (!accountId && accounts && accounts.length > 0) {
            form.setValue('account_id', accounts[0].id)
        }
    }, [accountId, accounts, form])

    // Auto-select most popular category if none selected (only for income/expense)
    useEffect(() => {
        if (!categoryId && transactionType !== 'transfer' && filteredCategories.length > 0) {
            form.setValue('category_id', filteredCategories[0].id)
        }
    }, [categoryId, transactionType, filteredCategories, form])

    // Calculate items total
    const itemsTotal = items?.reduce((sum, item) => {
        const qty = Number(item?.quantity) || 0
        const price = Number(item?.price_per_unit) || 0
        return sum + qty * price
    }, 0) ?? 0

    // Sync amount with items total
    useEffect(() => {
        if (items && items.length > 0 && itemsTotal > 0) {
            form.setValue('amount', itemsTotal, { shouldValidate: false })
        }
    }, [itemsTotal, items, form])

    // Reset category when type changes
    useEffect(() => {
        if (transactionType === 'transfer') {
            form.setValue('category_id', null)
        }
    }, [transactionType, form])

    // Refs for fast navigation
    const itemRefs = useRef<Map<string, HTMLInputElement>>(new Map())

    const addItem = useCallback(() => {
        append({ name: '', quantity: 1, price_per_unit: 0 })
        // Focus on new row's name field after render
        setTimeout(() => {
            const inputs = document.querySelectorAll('[data-item-name]')
            const lastInput = inputs[inputs.length - 1] as HTMLInputElement
            lastInput?.focus()
        }, 0)
    }, [append])

    const handleKeyDown = useCallback((
        e: KeyboardEvent<HTMLInputElement>,
        index: number,
        field: 'name' | 'quantity' | 'price_per_unit'
    ) => {
        // Enter on last field of row or Tab on price adds new row
        if (e.key === 'Enter' && field === 'price_per_unit') {
            e.preventDefault()
            addItem()
        }

        // Backspace on empty name removes row
        if (e.key === 'Backspace' && field === 'name') {
            const value = (e.target as HTMLInputElement).value
            if (value === '' && fields.length > 1) {
                e.preventDefault()
                remove(index)
                // Focus previous row
                setTimeout(() => {
                    const inputs = document.querySelectorAll('[data-item-name]')
                    const prevInput = inputs[Math.max(0, index - 1)] as HTMLInputElement
                    prevInput?.focus()
                }, 0)
            }
        }

        // Arrow down - next row same field
        if (e.key === 'ArrowDown' && index < fields.length - 1) {
            e.preventDefault()
            const nextInput = document.querySelector(
                `[data-item-${field}][data-index="${index + 1}"]`
            ) as HTMLInputElement
            nextInput?.focus()
        }

        // Arrow up - previous row same field
        if (e.key === 'ArrowUp' && index > 0) {
            e.preventDefault()
            const prevInput = document.querySelector(
                `[data-item-${field}][data-index="${index - 1}"]`
            ) as HTMLInputElement
            prevInput?.focus()
        }
    }, [addItem, remove, fields.length])

    const selectedAccount = accounts?.find(a => a.id === Number(accountId))
    const selectedToAccount = accounts?.find(a => a.id === Number(toAccountId))

    // Calculate balance preview
    const balancePreview = useMemo(() => {
        if (!selectedAccount) return null

        const currentBalance = selectedAccount.currentBalance
        const txAmount = Number(amount) || 0

        let newBalance = currentBalance
        if (transactionType === 'income') {
            newBalance = currentBalance + txAmount
        } else if (transactionType === 'expense' || transactionType === 'transfer') {
            newBalance = currentBalance - txAmount
        }

        const insufficientFunds = (transactionType === 'expense' || transactionType === 'transfer') && newBalance < 0

        return {
            currentBalance,
            newBalance,
            insufficientFunds,
            currency: selectedAccount.currency?.symbol ?? '',
        }
    }, [selectedAccount, amount, transactionType])

    // Balance preview for destination account (transfer)
    const toBalancePreview = useMemo(() => {
        if (!selectedToAccount || transactionType !== 'transfer') return null

        const currentBalance = selectedToAccount.currentBalance
        const txAmount = Number(toAmount) || Number(amount) || 0
        const newBalance = currentBalance + txAmount

        return {
            currentBalance,
            newBalance,
            currency: selectedToAccount.currency?.symbol ?? '',
        }
    }, [selectedToAccount, toAmount, amount, transactionType])

    return (
        <FormWrapper>
        <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                {/* Transaction Type Tabs */}
                <div className="flex gap-2 p-1 bg-muted rounded-lg">
                    {TRANSACTION_TYPES.map(({ value, label, icon: Icon, color }) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => {
                                form.setValue('type', value)
                                onTypeChange?.(value)
                            }}
                            className={cn(
                                'flex-1 flex items-center justify-center gap-2 py-2 px-4 rounded-md text-sm font-medium transition-all',
                                transactionType === value
                                    ? 'bg-background shadow-sm'
                                    : 'hover:bg-background/50'
                            )}
                        >
                            <Icon className={cn('size-4', transactionType === value && color)} />
                            {label}
                        </button>
                    ))}
                </div>

                <div className="grid grid-cols-2 gap-4">
                    {/* Account */}
                    <FormField
                        control={form.control}
                        name="account_id"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>
                                    {transactionType === 'transfer' ? 'From Account' : 'Account'}
                                </FormLabel>
                                <AccountSelect
                                    value={field.value}
                                    onChange={field.onChange}
                                />
                                <FormMessage />
                            </FormItem>
                        )}
                    />

                    {/* To Account (Transfer only) */}
                    {transactionType === 'transfer' ? (
                        <FormField
                            control={form.control}
                            name="to_account_id"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>To Account</FormLabel>
                                    <AccountSelect
                                        value={field.value}
                                        onChange={field.onChange}
                                        excludeId={Number(accountId)}
                                    />
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    ) : (
                        /* Category (Income/Expense only) */
                        <FormField
                            control={form.control}
                            name="category_id"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Category</FormLabel>
                                    <CategorySelect
                                        value={field.value}
                                        onChange={field.onChange}
                                        type={transactionType as 'income' | 'expense'}
                                    />
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    )}
                </div>

                {/* Balance Preview */}
                {balancePreview && (
                    <div className={cn(
                        'flex items-center gap-4 p-3 rounded-lg border text-sm',
                        balancePreview.insufficientFunds ? 'bg-destructive/10 border-destructive/50' : 'bg-muted/50'
                    )}>
                        <div className="flex-1">
                            <span className="text-muted-foreground">Balance: </span>
                            <span className="font-mono font-medium">
                                {balancePreview.currentBalance.toFixed(2)} {balancePreview.currency}
                            </span>
                        </div>
                        <span className="text-muted-foreground">→</span>
                        <div className="flex-1 text-right">
                            <span className="text-muted-foreground">After: </span>
                            <span className={cn(
                                'font-mono font-medium',
                                balancePreview.insufficientFunds ? 'text-destructive' :
                                    balancePreview.newBalance > balancePreview.currentBalance ? 'text-green-600' : 'text-foreground'
                            )}>
                                {balancePreview.newBalance.toFixed(2)} {balancePreview.currency}
                            </span>
                        </div>
                        {balancePreview.insufficientFunds && (
                            <span className="text-destructive text-xs font-medium">Insufficient funds</span>
                        )}
                    </div>
                )}

                {/* To Account Balance Preview (Transfer) */}
                {toBalancePreview && (
                    <div className="flex items-center gap-4 p-3 rounded-lg border bg-muted/50 text-sm">
                        <div className="flex-1">
                            <span className="text-muted-foreground">To Balance: </span>
                            <span className="font-mono font-medium">
                                {toBalancePreview.currentBalance.toFixed(2)} {toBalancePreview.currency}
                            </span>
                        </div>
                        <span className="text-muted-foreground">→</span>
                        <div className="flex-1 text-right">
                            <span className="text-muted-foreground">After: </span>
                            <span className="font-mono font-medium text-green-600">
                                {toBalancePreview.newBalance.toFixed(2)} {toBalancePreview.currency}
                            </span>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-2 gap-4">
                    {/* Amount */}
                    <FormField
                        control={form.control}
                        name="amount"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>
                                    {transactionType === 'transfer' ? 'Send Amount' : 'Amount'}
                                    {selectedAccount?.currency?.symbol && (
                                        <span className="text-muted-foreground ml-1">
                                            ({selectedAccount.currency.symbol})
                                        </span>
                                    )}
                                </FormLabel>
                                <FormControl>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        placeholder="0.00"
                                        {...field}
                                        disabled={items && items.length > 0}
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        )}
                    />

                    {/* To Amount (Transfer only) or Date */}
                    {transactionType === 'transfer' ? (
                        <FormField
                            control={form.control}
                            name="to_amount"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Receive Amount</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            placeholder="0.00 (auto if same currency)"
                                            {...field}
                                            value={field.value ?? ''}
                                            onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : null)}
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    ) : (
                        <FormField
                            control={form.control}
                            name="date"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Date</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="date"
                                            {...field}
                                            value={field.value || ''}
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    )}
                </div>

                {/* Date for transfer */}
                {transactionType === 'transfer' && (
                    <FormField
                        control={form.control}
                        name="date"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Date</FormLabel>
                                <FormControl>
                                    <Input
                                        type="date"
                                        {...field}
                                        value={field.value || ''}
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                )}

                {/* Description */}
                <FormField
                    control={form.control}
                    name="description"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Description</FormLabel>
                            <FormControl>
                                <Textarea
                                    placeholder="Optional notes..."
                                    className="resize-none h-20"
                                    {...field}
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                {/* Tags */}
                {tags && tags.length > 0 && (
                    <FormField
                        control={form.control}
                        name="tag_ids"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Tags</FormLabel>
                                <div className="flex flex-wrap gap-2">
                                    {tags.map((tag) => {
                                        const isSelected = selectedTagIds.includes(tag.id)
                                        return (
                                            <Badge
                                                key={tag.id}
                                                variant={isSelected ? 'default' : 'outline'}
                                                className={cn(
                                                    'cursor-pointer transition-colors',
                                                    isSelected
                                                        ? 'hover:bg-primary/80'
                                                        : 'hover:bg-muted'
                                                )}
                                                onClick={() => {
                                                    const newTagIds = isSelected
                                                        ? selectedTagIds.filter((id) => id !== tag.id)
                                                        : [...selectedTagIds, tag.id]
                                                    field.onChange(newTagIds)
                                                }}
                                            >
                                                #{tag.name}
                                            </Badge>
                                        )
                                    })}
                                </div>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                )}

                {/* Items (Expense only typically, but allow for Income) */}
                {transactionType !== 'transfer' && (
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <FormLabel>Items</FormLabel>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addItem}
                            >
                                <Plus className="size-4 mr-1" />
                                Add Item
                            </Button>
                        </div>

                        {fields.length > 0 && (
                            <div className="border rounded-lg overflow-hidden">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50">
                                        <tr>
                                            <th className="text-left p-2 font-medium">Name</th>
                                            <th className="text-left p-2 font-medium w-20">Qty</th>
                                            <th className="text-left p-2 font-medium w-28">Price</th>
                                            <th className="text-right p-2 font-medium w-28">Total</th>
                                            <th className="w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {fields.map((field, index) => {
                                            const qty = Number(items?.[index]?.quantity) || 0
                                            const price = Number(items?.[index]?.price_per_unit) || 0
                                            const total = qty * price

                                            return (
                                                <tr key={field.id} className="border-t">
                                                    <td className="p-1">
                                                        <Input
                                                            {...form.register(`items.${index}.name`)}
                                                            placeholder="Item name"
                                                            className="h-8 border-0 shadow-none focus-visible:ring-1"
                                                            data-item-name
                                                            data-index={index}
                                                            onKeyDown={(e) => handleKeyDown(e, index, 'name')}
                                                        />
                                                    </td>
                                                    <td className="p-1">
                                                        <Input
                                                            {...form.register(`items.${index}.quantity`)}
                                                            type="number"
                                                            step="1"
                                                            min={1}
                                                            placeholder="1"
                                                            className="h-8 border-0 shadow-none focus-visible:ring-1"
                                                            data-item-quantity
                                                            data-index={index}
                                                            onKeyDown={(e) => handleKeyDown(e, index, 'quantity')}
                                                        />
                                                    </td>
                                                    <td className="p-1">
                                                        <Input
                                                            {...form.register(`items.${index}.price_per_unit`)}
                                                            type="number"
                                                            step="0.01"
                                                            min={0}
                                                            placeholder="0.00"
                                                            className="h-8 border-0 shadow-none focus-visible:ring-1"
                                                            data-item-price_per_unit
                                                            data-index={index}
                                                            onKeyDown={(e) => handleKeyDown(e, index, 'price_per_unit')}
                                                        />
                                                    </td>
                                                    <td className="p-2 text-right font-mono text-muted-foreground">
                                                        {total.toFixed(2)}
                                                    </td>
                                                    <td className="p-1">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            onClick={() => remove(index)}
                                                            className="text-muted-foreground hover:text-destructive"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            )
                                        })}
                                    </tbody>
                                    <tfoot className="border-t bg-muted/30">
                                        <tr>
                                            <td colSpan={4} className="p-2 text-right font-medium">
                                                Total:
                                            </td>
                                            <td className="p-2 text-right font-mono font-semibold">
                                                {itemsTotal.toFixed(2)}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}

                        {fields.length === 0 && (
                            <p className="text-sm text-muted-foreground text-center py-4 border rounded-lg border-dashed">
                                No items. Click "Add Item" or enter amount manually.
                            </p>
                        )}

                        <FormField
                            control={form.control}
                            name="items"
                            render={() => <FormMessage />}
                        />
                    </div>
                )}

                <Button type="submit" disabled={isSubmitting || balancePreview?.insufficientFunds} className="w-full">
                    {isSubmitting ? 'Saving...' : submitLabel}
                </Button>
            </form>
        </Form>
        </FormWrapper>
    )
}
