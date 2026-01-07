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

interface CurrencyFormProps {
    defaultValues?: Partial<CurrencyFormData>
    onSubmit: (data: CurrencyFormData) => void
    isSubmitting?: boolean
    submitLabel?: string
    isEditing?: boolean
    autoUpdateEnabled?: boolean
    isBase?: boolean
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
                                <Input
                                    placeholder="USD"
                                    {...field}
                                    onChange={(e) => field.onChange(e.target.value.toUpperCase())}
                                />
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
                                <Input placeholder="US Dollar" {...field} />
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

                {isEditing && (
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
                                            ? 'Auto-update is enabled. Rates are updated automatically.'
                                            : 'Rate relative to base currency'}
                                </FormDescription>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                )}

                <Button type="submit" disabled={isSubmitting} className="w-full">
                    {isSubmitting ? 'Saving...' : submitLabel}
                </Button>
            </form>
        </Form>
        </FormWrapper>
    )
}
