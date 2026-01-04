import { FormPage } from '@/components/shared'
import { TransactionForm } from '@/components/features/transactions'
import { useCreateTransaction } from '@/hooks'
import { useSearchParams } from 'react-router-dom'
import { TransactionType } from '@/types'

export default function TransactionCreatePage() {
    const [searchParams] = useSearchParams()
    const createTransaction = useCreateTransaction('/transactions')

    const typeParam = searchParams.get('type')
    const accountIdParam = searchParams.get('account_id')

    const defaultType: TransactionType =
        typeParam === 'income' || typeParam === 'expense' || typeParam === 'transfer'
            ? typeParam
            : 'expense'

    const defaultAccountId = accountIdParam ? Number(accountIdParam) : undefined

    return (
        <FormPage title="New Transaction" backLink="/transactions">
            <TransactionForm
                defaultValues={{
                    type: defaultType,
                    account_id: defaultAccountId,
                }}
                onSubmit={(data) => createTransaction.mutate(data)}
                isSubmitting={createTransaction.isPending}
                submitLabel="Create"
            />
        </FormPage>
    )
}
