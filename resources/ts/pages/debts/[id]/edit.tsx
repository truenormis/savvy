import { useParams } from 'react-router-dom'
import { FormPage } from '@/components/shared'
import { DebtForm } from '@/components/features/debts'
import { useDebt, useUpdateDebt } from '@/hooks'

export default function DebtEditPage() {
    const { id } = useParams<{ id: string }>()
    const { data: debt, isLoading } = useDebt(id!)
    const updateDebt = useUpdateDebt('/debts')

    const defaultValues = debt
        ? {
              name: debt.name,
              debt_type: debt.debtType,
              currency_id: debt.currencyId,
              amount: debt.targetAmount,
              due_date: debt.dueDate ?? '',
              counterparty: debt.counterparty ?? '',
              description: debt.description ?? '',
          }
        : undefined

    return (
        <FormPage title="Edit Debt" backLink="/debts" isLoading={isLoading}>
            <DebtForm
                defaultValues={defaultValues}
                onSubmit={(data) => updateDebt.mutate({ id: id!, data })}
                isSubmitting={updateDebt.isPending}
                submitLabel="Save"
            />
        </FormPage>
    )
}
