import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Form,
    FormField,
    FormItem,
    FormLabel,
    FormControl,
    FormMessage,
    FormDescription,
} from '@/components/ui/form'
import { currencySchema, CurrencyFormData } from '@/schemas'
import { FormWrapper } from '@/components/shared/FormWrapper'
import { useCurrencyCatalog } from '@/hooks'
import type { CurrencyCatalogItem } from '@/types'

interface CurrencyFormProps {
    defaultValues?: Partial<CurrencyFormData>
    onSubmit: (data: CurrencyFormData) => void
    isSubmitting?: boolean
    submitLabel?: string
    isEditing?: boolean
    autoUpdateEnabled?: boolean
    isBase?: boolean
}

function filterCatalog(catalog: CurrencyCatalogItem[], query: string, field: 'code' | 'name') {
    const q = query.trim().toLowerCase()
    if (!q) {
        return []
    }

    return catalog
        .filter((item) =>
            field === 'code'
                ? item.code.toLowerCase().startsWith(q) || item.name.toLowerCase().includes(q)
                : item.name.toLowerCase().includes(q) || item.code.toLowerCase().startsWith(q)
        )
        .slice(0, 8)
}

function Suggestions({
    items,
    onSelect,
}: {
    items: CurrencyCatalogItem[]
    onSelect: (item: CurrencyCatalogItem) => void
}) {
    if (items.length === 0) {
        return null
    }

    return (
        <ul className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
            {items.map((item) => (
                <li key={item.code}>
                    <button
                        type="button"
                        className="flex w-full items-center justify-between gap-3 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => onSelect(item)}
                    >
                        <span className="min-w-0 truncate">
                            <span className="font-medium">{item.code}</span>
                            <span className="text-muted-foreground"> · {item.name}</span>
                        </span>
                        <span className="shrink-0 text-muted-foreground">{item.symbol}</span>
                    </button>
                </li>
            ))}
        </ul>
    )
}

export function CurrencyForm({
    defaultValues,
    onSubmit,
    isSubmitting,
    submitLabel = 'Save',
    isEditing = false,
    autoUpdateEnabled = false,
    isBase = false,
}: CurrencyFormProps) {
    const [suggestField, setSuggestField] = useState<'code' | 'name' | null>(null)
    const { data: catalog = [] } = useCurrencyCatalog(!isEditing)

    const form = useForm<CurrencyFormData>({
        resolver: zodResolver(currencySchema),
        defaultValues: {
            code: '',
            name: '',
            symbol: '',
            decimals: 2,
            rate: 1,
            ...defaultValues,
        },
    })

    const code = form.watch('code')
    const name = form.watch('name')

    const suggestions = useMemo(() => {
        if (isEditing || !suggestField) {
            return []
        }

        return filterCatalog(catalog, suggestField === 'code' ? code : name, suggestField)
    }, [catalog, code, isEditing, name, suggestField])

    const applyCatalogItem = (item: CurrencyCatalogItem) => {
        form.setValue('code', item.code, { shouldValidate: true, shouldDirty: true })
        form.setValue('name', item.name, { shouldValidate: true, shouldDirty: true })
        form.setValue('symbol', item.symbol, { shouldValidate: true, shouldDirty: true })
        form.setValue('decimals', item.decimals, { shouldValidate: true, shouldDirty: true })
        if (item.rate) {
            form.setValue('rate', item.rate, { shouldValidate: true, shouldDirty: true })
        }
        setSuggestField(null)
    }

    return (
        <FormWrapper>
        <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="max-w-md space-y-4">
                <FormField
                    control={form.control}
                    name="code"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Code</FormLabel>
                            <FormControl>
                                <div className="relative">
                                    <Input
                                        placeholder="USD"
                                        autoComplete="off"
                                        {...field}
                                        onChange={(e) => {
                                            field.onChange(e.target.value.toUpperCase())
                                            setSuggestField('code')
                                        }}
                                        onFocus={() => !isEditing && setSuggestField('code')}
                                        onBlur={() => setSuggestField((current) => current === 'code' ? null : current)}
                                    />
                                    {suggestField === 'code' && (
                                        <Suggestions items={suggestions} onSelect={applyCatalogItem} />
                                    )}
                                </div>
                            </FormControl>
                            <FormDescription>
                                ISO 4217 currency code (e.g., USD, EUR, RUB)
                            </FormDescription>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="name"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Name</FormLabel>
                            <FormControl>
                                <div className="relative">
                                    <Input
                                        placeholder="US Dollar"
                                        autoComplete="off"
                                        {...field}
                                        onChange={(e) => {
                                            field.onChange(e.target.value)
                                            setSuggestField('name')
                                        }}
                                        onFocus={() => !isEditing && setSuggestField('name')}
                                        onBlur={() => setSuggestField((current) => current === 'name' ? null : current)}
                                    />
                                    {suggestField === 'name' && (
                                        <Suggestions items={suggestions} onSelect={applyCatalogItem} />
                                    )}
                                </div>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="symbol"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Symbol</FormLabel>
                            <FormControl>
                                <Input placeholder="$" {...field} />
                            </FormControl>
                            <FormDescription>
                                Currency symbol for display (e.g., $, €, ₽)
                            </FormDescription>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="decimals"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Decimal places</FormLabel>
                            <FormControl>
                                <Input type="number" min={0} max={8} {...field} />
                            </FormControl>
                            <FormDescription>
                                Number of decimal places (usually 2)
                            </FormDescription>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="rate"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Exchange rate</FormLabel>
                            <FormControl>
                                <Input
                                    type="number"
                                    step="0.000001"
                                    min={0}
                                    disabled={isBase || autoUpdateEnabled}
                                    {...field}
                                />
                            </FormControl>
                            <FormDescription>
                                {isBase
                                    ? 'Base currency rate is always 1'
                                    : autoUpdateEnabled
                                        ? 'Filled from the catalog. Rates stay in sync automatically.'
                                        : 'Rate relative to base currency'}
                            </FormDescription>
                            <FormMessage />
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
