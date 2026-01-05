import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
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
    FormDescription,
} from '@/components/ui/form'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { debtSchema, DebtFormData } from '@/schemas'
import { useCurrencies } from '@/hooks'
import { Banknote, HandCoins } from 'lucide-react'

interface DebtFormProps {
    defaultValues?: Partial<DebtFormData>
    onSubmit: (data: DebtFormData) => void
    isSubmitting?: boolean
    submitLabel?: string
}

const DEBT_TYPES = [
    {
        value: 'i_owe',
        label: 'I Owe',
        icon: Banknote,
        description: 'Money you owe to someone',
        color: 'text-red-600'
    },
    {
        value: 'owed_to_me',
        label: 'Owed to Me',
        icon: HandCoins,
        description: 'Money someone owes to you',
        color: 'text-green-600'
    },
]

export function DebtForm({
    defaultValues,
    onSubmit,
    isSubmitting,
    submitLabel = 'Save',
}: DebtFormProps) {
    const { data: currencies, isLoading: currenciesLoading } = useCurrencies()

    const form = useForm<DebtFormData>({
        resolver: zodResolver(debtSchema),
        defaultValues: {
            name: '',
            debt_type: 'i_owe',
            currency_id: 0,
            amount: 0,
            due_date: '',
            counterparty: '',
            description: '',
            ...defaultValues,
        },
    })

    return (
        <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="max-w-md space-y-4">
                <FormField
                    control={form.control}
                    name="name"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Name</FormLabel>
                            <FormControl>
                                <Input placeholder="Car Loan" {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="debt_type"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Type</FormLabel>
                            <Select onValueChange={field.onChange} defaultValue={field.value}>
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select debt type" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    {DEBT_TYPES.map((type) => {
                                        const Icon = type.icon
                                        return (
                                            <SelectItem key={type.value} value={type.value}>
                                                <div className="flex items-center gap-2">
                                                    <Icon className={`size-4 ${type.color}`} />
                                                    <span>{type.label}</span>
                                                </div>
                                            </SelectItem>
                                        )
                                    })}
                                </SelectContent>
                            </Select>
                            <FormDescription>
                                {form.watch('debt_type') === 'i_owe'
                                    ? 'Money you need to pay back'
                                    : 'Money you expect to receive'
                                }
                            </FormDescription>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="currency_id"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Currency</FormLabel>
                            <Select
                                onValueChange={field.onChange}
                                defaultValue={field.value?.toString()}
                                disabled={currenciesLoading}
                            >
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select currency" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    {currencies?.map((currency) => (
                                        <SelectItem
                                            key={currency.id}
                                            value={currency.id.toString()}
                                        >
                                            <span className="font-mono">{currency.code}</span>
                                            <span className="text-muted-foreground ml-2">
                                                {currency.symbol} · {currency.name}
                                            </span>
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
                            <FormLabel>Amount</FormLabel>
                            <FormControl>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    placeholder="1000.00"
                                    {...field}
                                />
                            </FormControl>
                            <FormDescription>
                                Total debt amount
                            </FormDescription>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="counterparty"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Counterparty</FormLabel>
                            <FormControl>
                                <Input placeholder="John Doe / Bank Name" {...field} />
                            </FormControl>
                            <FormDescription>
                                Who you owe to or who owes you
                            </FormDescription>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="due_date"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Due Date</FormLabel>
                            <FormControl>
                                <Input type="date" {...field} />
                            </FormControl>
                            <FormDescription>
                                Optional deadline for the debt
                            </FormDescription>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="description"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Description</FormLabel>
                            <FormControl>
                                <Textarea
                                    placeholder="Additional notes about this debt..."
                                    {...field}
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <Button type="submit" disabled={isSubmitting} className="w-full">
                    {isSubmitting ? 'Saving...' : submitLabel}
                </Button>
            </form>
        </Form>
    )
}
