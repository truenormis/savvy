import { FormPage } from '@/components/shared'
import { BudgetForm } from '@/components/features/budgets'
import { useCreateBudget } from '@/hooks'

export default function BudgetCreatePage() {
    const createBudget = useCreateBudget('/budgets')

    return (
        <FormPage title="Create Budget" backLink="/budgets">
            <BudgetForm
                onSubmit={(data) => createBudget.mutate(data)}
                isSubmitting={createBudget.isPending}
                submitLabel="Create"
            />
        </FormPage>
    )
}
