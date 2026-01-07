import { useQueryState, parseAsStringLiteral } from 'nuqs'
import { FormPage } from '@/components/shared'
import { TransactionForm } from '@/components/features/transactions'
import { useCreateTransaction } from '@/hooks'
import { TransactionType } from '@/types'

export default function TransactionCreatePage() {
    const [type, setType] = useQueryState(
        'type',
        parseAsStringLiteral(['income', 'expense', 'transfer'] as const).withDefault('expense')
    )
    const createTransaction = useCreateTransaction('/transactions')

    return (
        <FormPage title="New Transaction" backLink="/transactions">
            <TransactionForm
                defaultValues={{ type: type as TransactionType }}
                onTypeChange={setType}
                onSubmit={(data) => createTransaction.mutate(data)}
                isSubmitting={createTransaction.isPending}
                submitLabel="Create"
            />
        </FormPage>
    )
}
