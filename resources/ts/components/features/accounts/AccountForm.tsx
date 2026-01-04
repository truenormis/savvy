import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
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
import { accountSchema, AccountFormData } from '@/schemas'
import { useCurrencies } from '@/hooks'
import { Landmark, Wallet, Bitcoin } from 'lucide-react'

const ACCOUNT_TYPES = [
    { value: 'bank', label: 'Bank Account', icon: Landmark },
    { value: 'cash', label: 'Cash', icon: Wallet },
    { value: 'crypto', label: 'Crypto', icon: Bitcoin },
] as const

interface AccountFormProps {
    defaultValues?: Partial<AccountFormData>
    onSubmit: (data: AccountFormData) => void
    isSubmitting?: boolean
    submitLabel?: string
}

export function AccountForm({
    defaultValues,
    onSubmit,
    isSubmitting,
    submitLabel = 'Save',
}: AccountFormProps) {
    const { data: currencies, isLoading: currenciesLoading } = useCurrencies()

    const form = useForm<AccountFormData>({
        resolver: zodResolver(accountSchema),
        defaultValues: {
            name: '',
            type: 'bank',
            currency_id: 0,
            initial_balance: 0,
            is_active: true,
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
                                <Input placeholder="My Account" {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="type"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Type</FormLabel>
                            <Select onValueChange={field.onChange} defaultValue={field.value}>
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select account type" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    {ACCOUNT_TYPES.map(({ value, label, icon: Icon }) => (
                                        <SelectItem key={value} value={value}>
                                            <div className="flex items-center gap-2">
                                                <Icon className="size-4" />
                                                {label}
                                            </div>
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
                    name="initial_balance"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Initial Balance</FormLabel>
                            <FormControl>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    placeholder="0.00"
                                    {...field}
                                />
                            </FormControl>
                            <FormDescription>
                                Starting balance for this account
                            </FormDescription>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="is_active"
                    render={({ field }) => (
                        <FormItem className="flex items-center justify-between rounded-lg border p-4">
                            <div className="space-y-0.5">
                                <FormLabel className="text-base">Active</FormLabel>
                                <FormDescription>
                                    Inactive accounts are hidden from lists
                                </FormDescription>
                            </div>
                            <FormControl>
                                <Switch
                                    checked={field.value}
                                    onCheckedChange={field.onChange}
                                />
                            </FormControl>
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
