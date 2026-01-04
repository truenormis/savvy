import { useParams } from 'react-router-dom'
import { FormPage } from '@/components/shared'
import { BudgetForm } from '@/components/features/budgets'
import { useBudget, useUpdateBudget } from '@/hooks'
import { Loader2 } from 'lucide-react'

export default function BudgetEditPage() {
    const { id } = useParams<{ id: string }>()
    const { data: budget, isLoading } = useBudget(id!)
    const updateBudget = useUpdateBudget('/budgets')

    if (isLoading) {
        return (
            <div className="flex items-center justify-center min-h-[200px]">
                <Loader2 className="size-8 animate-spin text-muted-foreground" />
            </div>
        )
    }

    if (!budget) {
        return <div>Budget not found</div>
    }

    return (
        <FormPage title="Edit Budget" backLink="/budgets">
            <BudgetForm
                defaultValues={{
                    name: budget.name,
                    amount: budget.amount,
                    currency_id: budget.currencyId,
                    period: budget.period,
                    start_date: budget.startDate,
                    end_date: budget.endDate,
                    is_global: budget.isGlobal,
                    notify_at_percent: budget.notifyAtPercent,
                    is_active: budget.isActive,
                    category_ids: budget.categories.map(c => c.id),
                    tag_ids: budget.tags?.map(t => t.id) ?? [],
                }}
                onSubmit={(data) => updateBudget.mutate({ id: id!, data })}
                isSubmitting={updateBudget.isPending}
                submitLabel="Update"
            />
        </FormPage>
    )
}
