import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Form,
    FormField,
    FormItem,
    FormLabel,
    FormControl,
    FormMessage,
    FormDescription,
} from '@/components/ui/form'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { recurringSchema, RecurringFormData } from '@/schemas'
import { useAccounts, useCategories, useTags } from '@/hooks'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { ArrowDownLeft, ArrowUpRight, ArrowLeftRight } from 'lucide-react'
import { AccountSelect } from '@/components/shared/AccountSelect'
import { CategorySelect } from '@/components/shared/CategorySelect'
import { FormWrapper } from '@/components/shared/FormWrapper'

interface RecurringFormProps {
    defaultValues?: Partial<RecurringFormData>
    onSubmit: (data: RecurringFormData) => void
    isSubmitting?: boolean
    submitLabel?: string
}

const typeOptions = [
    { value: 'income', label: 'Income', icon: ArrowDownLeft, color: 'text-green-500' },
    { value: 'expense', label: 'Expense', icon: ArrowUpRight, color: 'text-red-500' },
    { value: 'transfer', label: 'Transfer', icon: ArrowLeftRight, color: 'text-blue-500' },
] as const

const frequencyOptions = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'yearly', label: 'Yearly' },
]

const dayOfWeekOptions = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
]

export function RecurringForm({
    defaultValues,
    onSubmit,
    isSubmitting,
    submitLabel = 'Save',
}: RecurringFormProps) {
    const { data: accounts } = useAccounts({ active: true, exclude_debts: true })
    const { data: categories } = useCategories()
    const { data: tags } = useTags()

    const form = useForm<RecurringFormData>({
        resolver: zodResolver(recurringSchema),
        defaultValues: {
            type: 'expense',
            account_id: 0,
            to_account_id: null,
            category_id: null,
            amount: 0,
            to_amount: null,
            description: '',
            frequency: 'monthly',
            interval: 1,
            day_of_week: null,
            day_of_month: 1,
            start_date: new Date().toISOString().split('T')[0],
            end_date: null,
            is_active: true,
            tag_ids: [],
            ...defaultValues,
        },
    })

    const type = form.watch('type')
    const frequency = form.watch('frequency')
    const selectedTagIds = form.watch('tag_ids') ?? []
    const accountId = form.watch('account_id')

    const isTransfer = type === 'transfer'
    const selectedAccount = accounts?.find(a => a.id === accountId)

    // Auto-select first account
    useEffect(() => {
        if (!accountId && accounts && accounts.length > 0) {
            form.setValue('account_id', accounts[0].id)
        }
    }, [accountId, accounts, form])

    // Auto-select most popular category
    useEffect(() => {
        const filteredCategories = categories?.filter(c => c.type === type) ?? []
        const sorted = [...filteredCategories].sort((a, b) =>
            (b.transactionsCount ?? 0) - (a.transactionsCount ?? 0)
        )
        const currentCategoryId = form.getValues('category_id')
        if (!currentCategoryId && type !== 'transfer' && sorted.length > 0) {
            form.setValue('category_id', sorted[0].id)
        }
    }, [type, categories, form])

    return (
        <FormWrapper>
        <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="max-w-lg space-y-4">
                {/* Type Selection */}
                <FormField
                    control={form.control}
                    name="type"
                    render={({ field }) => (
                        <FormItem>
                            <Tabs
                                value={field.value}
                                onValueChange={(value) => {
                                    field.onChange(value)
                                    if (value === 'transfer') {
                                        form.setValue('category_id', null)
                                    } else {
                                        form.setValue('to_account_id', null)
                                        form.setValue('to_amount', null)
                                    }
                                }}
                                className="w-full"
                            >
                                <TabsList className="w-full">
                                    {typeOptions.map(({ value, label, icon: Icon, color }) => (
                                        <TabsTrigger key={value} value={value} className="flex-1">
                                            <Icon className={cn('size-4', field.value === value && color)} />
                                            {label}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </Tabs>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                {/* Account Selection */}
                <div className={cn('grid gap-4', isTransfer ? 'grid-cols-2' : 'grid-cols-1')}>
                    <FormField
                        control={form.control}
                        name="account_id"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>{isTransfer ? 'From Account' : 'Account'}</FormLabel>
                                <AccountSelect
                                    value={field.value}
                                    onChange={field.onChange}
                                />
                                <FormMessage />
                            </FormItem>
                        )}
                    />

                    {isTransfer && (
                        <FormField
                            control={form.control}
                            name="to_account_id"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>To Account</FormLabel>
                                    <AccountSelect
                                        value={field.value}
                                        onChange={field.onChange}
                                        excludeId={accountId}
                                    />
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    )}
                </div>

                {/* Category (for income/expense) */}
                {!isTransfer && (
                    <FormField
                        control={form.control}
                        name="category_id"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Category</FormLabel>
                                <CategorySelect
                                    value={field.value}
                                    onChange={field.onChange}
                                    type={type as 'income' | 'expense'}
                                />
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                )}

                {/* Amount */}
                <div className={cn('grid gap-4', isTransfer ? 'grid-cols-2' : 'grid-cols-1')}>
                    <FormField
                        control={form.control}
                        name="amount"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>
                                    Amount
                                    {selectedAccount?.currency?.symbol && (
                                        <span className="text-muted-foreground ml-1">
                                            ({selectedAccount.currency.symbol})
                                        </span>
                                    )}
                                </FormLabel>
                                <FormControl>
                                    <Input type="number" step="0.01" min="0" {...field} />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        )}
                    />

                    {isTransfer && (
                        <FormField
                            control={form.control}
                            name="to_amount"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>To Amount</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="Same as amount"
                                            {...field}
                                            value={field.value ?? ''}
                                            onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : null)}
                                        />
                                    </FormControl>
                                    <FormDescription>Leave empty for same currency</FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    )}
                </div>

                {/* Description */}
                <FormField
                    control={form.control}
                    name="description"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Description</FormLabel>
                            <FormControl>
                                <Textarea
                                    placeholder="e.g., Monthly rent payment"
                                    className="resize-none h-20"
                                    {...field}
                                    value={field.value ?? ''}
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                {/* Schedule Section */}
                <div className="space-y-4 pt-4 border-t">
                    <h3 className="font-medium">Schedule</h3>

                    <div className="grid grid-cols-2 gap-4">
                        <FormField
                            control={form.control}
                            name="frequency"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Frequency</FormLabel>
                                    <Select onValueChange={field.onChange} value={field.value}>
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            {frequencyOptions.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="interval"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Every</FormLabel>
                                    <FormControl>
                                        <Input type="number" min="1" max="365" {...field} />
                                    </FormControl>
                                    <FormDescription>
                                        {frequency === 'daily' && 'day(s)'}
                                        {frequency === 'weekly' && 'week(s)'}
                                        {frequency === 'monthly' && 'month(s)'}
                                        {frequency === 'yearly' && 'year(s)'}
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </div>

                    {frequency === 'weekly' && (
                        <FormField
                            control={form.control}
                            name="day_of_week"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Day of Week</FormLabel>
                                    <Select
                                        onValueChange={(val) => field.onChange(Number(val))}
                                        value={field.value?.toString() ?? ''}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select day" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            {dayOfWeekOptions.map((option) => (
                                                <SelectItem key={option.value} value={option.value.toString()}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    )}

                    {frequency === 'monthly' && (
                        <FormField
                            control={form.control}
                            name="day_of_month"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Day of Month</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="number"
                                            min="1"
                                            max="31"
                                            {...field}
                                            value={field.value ?? ''}
                                            onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : null)}
                                        />
                                    </FormControl>
                                    <FormDescription>1-31 (adjusted for shorter months)</FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    )}

                    <div className="grid grid-cols-2 gap-4">
                        <FormField
                            control={form.control}
                            name="start_date"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Start Date</FormLabel>
                                    <FormControl>
                                        <Input type="date" {...field} />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="end_date"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>End Date</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="date"
                                            {...field}
                                            value={field.value ?? ''}
                                            onChange={(e) => field.onChange(e.target.value || null)}
                                        />
                                    </FormControl>
                                    <FormDescription>Leave empty for no end</FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </div>
                </div>

                {/* Tags */}
                {tags && tags.length > 0 && (
                    <FormField
                        control={form.control}
                        name="tag_ids"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Tags</FormLabel>
                                <div className="flex flex-wrap gap-2 p-3 rounded-md border">
                                    {tags.map((tag) => {
                                        const isSelected = selectedTagIds.includes(tag.id)
                                        return (
                                            <Badge
                                                key={tag.id}
                                                variant={isSelected ? 'default' : 'outline'}
                                                className={cn(
                                                    'cursor-pointer transition-colors',
                                                    isSelected ? 'hover:bg-primary/80' : 'hover:bg-muted'
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

                {/* Active */}
                <FormField
                    control={form.control}
                    name="is_active"
                    render={({ field }) => (
                        <FormItem className="flex flex-row items-center space-x-3 space-y-0">
                            <FormControl>
                                <Checkbox
                                    checked={field.value}
                                    onCheckedChange={field.onChange}
                                />
                            </FormControl>
                            <FormLabel>Active</FormLabel>
                        </FormItem>
                    )}
                />

                <Button type="submit" disabled={isSubmitting} className="w-full">
                    {isSubmitting ? 'Saving...' : submitLabel}
                </Button>
            </form>
        </Form>
        </FormWrapper>
    )
}
