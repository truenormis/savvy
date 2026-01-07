import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
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
import { AccountSelect } from '@/components/shared/AccountSelect'
import type { CsvParseResult, ColumnMapping, ImportOptions, DateFormat, AmountFormat } from '@/types/import'
import { useEffect } from 'react'

const mappingSchema = z.object({
    date: z.number({ required_error: 'Date column is required' }),
    amount: z.number({ required_error: 'Amount column is required' }),
    description: z.number().nullable(),
    type: z.number().nullable(),
    category: z.number().nullable(),
    tags: z.number().nullable(),
    currency: z.number().nullable(),
    dateFormat: z.string(),
    amountFormat: z.string(),
    defaultAccountId: z.number({ required_error: 'Account is required' }),
    defaultType: z.enum(['income', 'expense']),
})

type MappingFormValues = z.infer<typeof mappingSchema>

interface MappingStepProps {
    parseResult: CsvParseResult
    onSubmit: (mapping: ColumnMapping, options: ImportOptions) => void
    isLoading: boolean
}

const NONE_VALUE = '__none__'

export function MappingStep({ parseResult, onSubmit, isLoading }: MappingStepProps) {
    const form = useForm<MappingFormValues>({
        resolver: zodResolver(mappingSchema),
        defaultValues: {
            date: parseResult.suggestedMapping.date ?? undefined,
            amount: parseResult.suggestedMapping.amount ?? undefined,
            description: parseResult.suggestedMapping.description ?? null,
            type: parseResult.suggestedMapping.type ?? null,
            category: parseResult.suggestedMapping.category ?? null,
            tags: parseResult.suggestedMapping.tags ?? null,
            currency: parseResult.suggestedMapping.currency ?? null,
            dateFormat: parseResult.detectedFormats.dateFormat,
            amountFormat: parseResult.detectedFormats.amountFormat,
            defaultAccountId: undefined,
            defaultType: 'expense',
        },
    })

    // Auto-submit when form is valid and user changes values
    useEffect(() => {
        const subscription = form.watch(() => {
            // Don't auto-submit, just track changes
        })
        return () => subscription.unsubscribe()
    }, [form])

    const handleSubmit = (values: MappingFormValues) => {
        const mapping: ColumnMapping = {
            date: values.date,
            amount: values.amount,
            description: values.description,
            type: values.type,
            category: values.category,
            tags: values.tags,
            currency: values.currency,
        }

        const options: ImportOptions = {
            dateFormat: values.dateFormat as DateFormat,
            amountFormat: values.amountFormat as AmountFormat,
            defaultAccountId: values.defaultAccountId,
            defaultType: values.defaultType,
            skipFirstRow: parseResult.detectedFormats.hasHeader,
            createMissingCurrencies: true,
            createMissingTags: true,
            createMissingCategories: true,
        }

        onSubmit(mapping, options)
    }

    const columnOptions = parseResult.headers.map((header, index) => ({
        value: index.toString(),
        label: `${index + 1}: ${header}`,
    }))

    return (
        <Form {...form}>
            <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6" id="mapping-form">
                {/* Required Mappings */}
                <div className="space-y-4">
                    <h3 className="font-medium">Required Fields</h3>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            control={form.control}
                            name="date"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Date Column *</FormLabel>
                                    <Select
                                        onValueChange={(val) => field.onChange(Number(val))}
                                        value={field.value?.toString()}
                                        disabled={isLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select column" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            {columnOptions.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value}>
                                                    {opt.label}
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
                            name="amount"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Amount Column *</FormLabel>
                                    <Select
                                        onValueChange={(val) => field.onChange(Number(val))}
                                        value={field.value?.toString()}
                                        disabled={isLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select column" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            {columnOptions.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </div>

                    <FormField
                        control={form.control}
                        name="defaultAccountId"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Target Account *</FormLabel>
                                <AccountSelect
                                    value={field.value}
                                    onChange={field.onChange}
                                    placeholder="Select account for import"
                                    disabled={isLoading}
                                />
                                <FormDescription>
                                    All imported transactions will be added to this account
                                </FormDescription>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                </div>

                {/* Optional Mappings */}
                <div className="space-y-4">
                    <h3 className="font-medium">Optional Fields</h3>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            control={form.control}
                            name="description"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Description Column</FormLabel>
                                    <Select
                                        onValueChange={(val) => field.onChange(val === NONE_VALUE ? null : Number(val))}
                                        value={field.value?.toString() ?? NONE_VALUE}
                                        disabled={isLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select column" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value={NONE_VALUE}>-- None --</SelectItem>
                                            {columnOptions.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value}>
                                                    {opt.label}
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
                            name="category"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Category Column</FormLabel>
                                    <Select
                                        onValueChange={(val) => field.onChange(val === NONE_VALUE ? null : Number(val))}
                                        value={field.value?.toString() ?? NONE_VALUE}
                                        disabled={isLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select column" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value={NONE_VALUE}>-- None --</SelectItem>
                                            {columnOptions.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FormDescription>
                                        Categories will be auto-created if not found
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="tags"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Tags Column</FormLabel>
                                    <Select
                                        onValueChange={(val) => field.onChange(val === NONE_VALUE ? null : Number(val))}
                                        value={field.value?.toString() ?? NONE_VALUE}
                                        disabled={isLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select column" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value={NONE_VALUE}>-- None --</SelectItem>
                                            {columnOptions.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FormDescription>
                                        Multiple tags separated by comma
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="type"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Type Column</FormLabel>
                                    <Select
                                        onValueChange={(val) => field.onChange(val === NONE_VALUE ? null : Number(val))}
                                        value={field.value?.toString() ?? NONE_VALUE}
                                        disabled={isLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select column" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value={NONE_VALUE}>-- Auto-detect from amount --</SelectItem>
                                            {columnOptions.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FormDescription>
                                        If not set, type is determined by amount sign
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </div>
                </div>

                {/* Format Options */}
                <div className="space-y-4">
                    <h3 className="font-medium">Format Settings</h3>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            control={form.control}
                            name="dateFormat"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Date Format</FormLabel>
                                    <Select
                                        onValueChange={field.onChange}
                                        value={field.value}
                                        disabled={isLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value="ISO">ISO (YYYY-MM-DD)</SelectItem>
                                            <SelectItem value="DD.MM.YYYY">DD.MM.YYYY</SelectItem>
                                            <SelectItem value="DD/MM/YYYY">DD/MM/YYYY</SelectItem>
                                            <SelectItem value="MM/DD/YYYY">MM/DD/YYYY</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="amountFormat"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Amount Format</FormLabel>
                                    <Select
                                        onValueChange={field.onChange}
                                        value={field.value}
                                        disabled={isLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value="US">US (1,234.56)</SelectItem>
                                            <SelectItem value="EU">EU (1.234,56)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="defaultType"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Default Type</FormLabel>
                                    <Select
                                        onValueChange={field.onChange}
                                        value={field.value}
                                        disabled={isLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value="expense">Expense</SelectItem>
                                            <SelectItem value="income">Income</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormDescription>
                                        Used for zero amounts
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </div>
                </div>
            </form>
        </Form>
    )
}
