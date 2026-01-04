import { ListPage } from '@/components/shared'
import { createBudgetColumns } from '@/components/features/budgets'
import { useBudgets, useDeleteBudget } from '@/hooks'

export default function BudgetsPage() {
    const { data: budgets, isLoading } = useBudgets()
    const deleteBudget = useDeleteBudget()

    const columns = createBudgetColumns((id) => deleteBudget.mutate(id))

    return (
        <ListPage
            title="Budgets"
            description="Set spending limits for categories"
            createLink="/budgets/create"
            createLabel="New Budget"
            data={budgets ?? []}
            columns={columns}
            isLoading={isLoading}
        />
    )
}
