import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, HandCoins, Banknote, TrendingDown, TrendingUp } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { DataTable } from '@/components/shared'
import { createDebtColumns, DebtPaymentDialog } from '@/components/features/debts'
import { useDebtsWithSummary, useDeleteDebt, useDebtPayment, useDebtCollection, useReopenDebt } from '@/hooks'
import { Debt, DebtPaymentFormData } from '@/types'

export default function DebtsPage() {
    const [includeCompleted, setIncludeCompleted] = useState(false)
    const [paymentDialogOpen, setPaymentDialogOpen] = useState(false)
    const [selectedDebt, setSelectedDebt] = useState<Debt | null>(null)
    const [paymentMode, setPaymentMode] = useState<'payment' | 'collection'>('payment')

    const { data, isLoading } = useDebtsWithSummary({ include_completed: includeCompleted })
    const deleteDebt = useDeleteDebt()
    const debtPayment = useDebtPayment()
    const debtCollection = useDebtCollection()
    const reopenDebt = useReopenDebt()

    const debts = data?.data ?? []
    const summary = data?.summary

    const handlePayment = (debt: Debt) => {
        setSelectedDebt(debt)
        setPaymentMode('payment')
        setPaymentDialogOpen(true)
    }

    const handleCollect = (debt: Debt) => {
        setSelectedDebt(debt)
        setPaymentMode('collection')
        setPaymentDialogOpen(true)
    }

    const handlePaymentSubmit = (debtId: number, formData: DebtPaymentFormData) => {
        if (paymentMode === 'payment') {
            debtPayment.mutate(
                { debtId, data: formData },
                { onSuccess: () => setPaymentDialogOpen(false) }
            )
        } else {
            debtCollection.mutate(
                { debtId, data: formData },
                { onSuccess: () => setPaymentDialogOpen(false) }
            )
        }
    }

    const columns = createDebtColumns({
        onDelete: (id) => deleteDebt.mutate(id),
        onPayment: handlePayment,
        onCollect: handleCollect,
        onReopen: (id) => reopenDebt.mutate(id),
    })

    const formatCurrency = (amount: number) => {
        if (!summary?.currency) return amount.toFixed(2)
        return `${summary.currency} ${amount.toFixed(2)}`
    }

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold">Debts</h1>
                    <p className="text-muted-foreground">Track money you owe and money owed to you</p>
                </div>
                <Button asChild>
                    <Link to="/debts/create">
                        <Plus className="mr-2 size-4" />
                        New Debt
                    </Link>
                </Button>
            </div>

            {summary && (
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center gap-2">
                            <div className="p-2 rounded-lg bg-red-100">
                                <TrendingDown className="size-5 text-red-600" />
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">I Owe</p>
                                <p className="text-2xl font-bold text-red-600">
                                    {formatCurrency(summary.total_i_owe)}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center gap-2">
                            <div className="p-2 rounded-lg bg-green-100">
                                <TrendingUp className="size-5 text-green-600" />
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Owed to Me</p>
                                <p className="text-2xl font-bold text-green-600">
                                    {formatCurrency(summary.total_owed_to_me)}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center gap-2">
                            <div className={`p-2 rounded-lg ${summary.net_debt >= 0 ? 'bg-green-100' : 'bg-red-100'}`}>
                                {summary.net_debt >= 0 ? (
                                    <HandCoins className="size-5 text-green-600" />
                                ) : (
                                    <Banknote className="size-5 text-red-600" />
                                )}
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Net Position</p>
                                <p className={`text-2xl font-bold ${summary.net_debt >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {formatCurrency(Math.abs(summary.net_debt))}
                                    {summary.net_debt >= 0 ? ' in your favor' : ' you owe'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <div className="flex items-center space-x-2">
                <Switch
                    id="include-completed"
                    checked={includeCompleted}
                    onCheckedChange={setIncludeCompleted}
                />
                <Label htmlFor="include-completed">Show completed debts</Label>
            </div>

            <DataTable
                columns={columns}
                data={debts}
                isLoading={isLoading}
                searchColumn="name"
                searchPlaceholder="Search debts..."
            />

            <DebtPaymentDialog
                debt={selectedDebt}
                open={paymentDialogOpen}
                onOpenChange={setPaymentDialogOpen}
                onSubmit={handlePaymentSubmit}
                isSubmitting={debtPayment.isPending || debtCollection.isPending}
                mode={paymentMode}
            />
        </div>
    )
}
