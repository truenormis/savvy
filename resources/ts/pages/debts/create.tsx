import { FormPage } from '@/components/shared'
import { DebtForm } from '@/components/features/debts'
import { useCreateDebt } from '@/hooks'

export default function DebtCreatePage() {
    const createDebt = useCreateDebt('/debts')

    return (
        <FormPage title="Create Debt" backLink="/debts">
            <DebtForm
                onSubmit={(data) => createDebt.mutate(data)}
                isSubmitting={createDebt.isPending}
                submitLabel="Create"
            />
        </FormPage>
    )
}
