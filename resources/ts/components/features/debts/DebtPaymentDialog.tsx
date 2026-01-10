import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
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
import { debtPaymentSchema, DebtPaymentFormData } from '@/schemas'
import { useAccounts } from '@/hooks'
import { Debt } from '@/types'

interface DebtPaymentDialogProps {
    debt: Debt | null
    open: boolean
    onOpenChange: (open: boolean) => void
    onSubmit: (debtId: number, data: DebtPaymentFormData) => void
    isSubmitting?: boolean
    mode: 'payment' | 'collection'
}

export function DebtPaymentDialog({
    debt,
    open,
    onOpenChange,
    onSubmit,
    isSubmitting,
    mode,
}: DebtPaymentDialogProps) {
    const { data: accounts, isLoading: accountsLoading } = useAccounts({ active: true, exclude_debts: true })

    const form = useForm<DebtPaymentFormData>({
        resolver: zodResolver(debtPaymentSchema),
        defaultValues: {
            account_id: 0,
            amount: 0,
            date: new Date().toISOString().split('T')[0],
            description: '',
        },
    })

    const handleSubmit = (data: DebtPaymentFormData) => {
        if (debt) {
            onSubmit(debt.id, data)
        }
    }

    const formatAmount = (amount: number) => {
        if (!debt?.currency) return amount.toFixed(2)
        return `${debt.currency.symbol}${amount.toFixed(debt.currency.decimals)}`
    }

    const title = mode === 'payment' ? 'Make Payment' : 'Collect Payment'
    const description = mode === 'payment'
        ? 'Record a payment towards this debt'
        : 'Record a payment you received'

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                {debt && (
                    <div className="rounded-lg bg-muted p-4 space-y-2">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Debt</span>
                            <span className="font-medium">{debt.name}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Remaining</span>
                            <span className="font-mono">{formatAmount(debt.remainingDebt)}</span>
                        </div>
                        {debt.counterparty && (
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">
                                    {mode === 'payment' ? 'Pay to' : 'Receive from'}
                                </span>
                                <span>{debt.counterparty}</span>
                            </div>
                        )}
                    </div>
                )}

                <Form {...form}>
                    <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
                        <FormField
                            control={form.control}
                            name="account_id"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Account</FormLabel>
                                    <Select
                                        onValueChange={field.onChange}
                                        defaultValue={field.value?.toString()}
                                        disabled={accountsLoading}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select account" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            {accounts?.map((account) => (
                                                <SelectItem
                                                    key={account.id}
                                                    value={account.id.toString()}
                                                >
                                                    <div className="flex items-center justify-between gap-4">
                                                        <span>{account.name}</span>
                                                        <span className="text-muted-foreground text-xs font-mono">
                                                            {account.currency?.symbol}{account.currentBalance.toFixed(account.currency?.decimals ?? 2)}
                                                        </span>
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FormDescription>
                                        {mode === 'payment'
                                            ? 'Account to pay from'
                                            : 'Account to receive into'
                                        }
                                    </FormDescription>
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
                                            max={debt?.remainingDebt}
                                            placeholder="0.00"
                                            {...field}
                                        />
                                    </FormControl>
                                    {debt && (
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => form.setValue('amount', debt.remainingDebt)}
                                            >
                                                Full Amount
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => form.setValue('amount', Math.round(debt.remainingDebt / 2 * 100) / 100)}
                                            >
                                                Half
                                            </Button>
                                        </div>
                                    )}
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="date"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Date</FormLabel>
                                    <FormControl>
                                        <Input type="date" {...field} />
                                    </FormControl>
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
                                            placeholder="Optional notes..."
                                            {...field}
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={isSubmitting}>
                                {isSubmitting
                                    ? 'Processing...'
                                    : mode === 'payment' ? 'Make Payment' : 'Collect Payment'
                                }
                            </Button>
                        </DialogFooter>
                    </form>
                </Form>
            </DialogContent>
        </Dialog>
    )
}
