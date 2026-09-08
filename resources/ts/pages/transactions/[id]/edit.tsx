import { useParams } from 'react-router-dom'
import { useQueryState, parseAsStringLiteral } from 'nuqs'
import { FormPage } from '@/components/shared'
import { TransactionForm } from '@/components/features/transactions'
import { useTransaction, useUpdateTransaction } from '@/hooks'
import { TransactionFormType } from '@/types'

export default function TransactionEditPage() {
    const { id } = useParams<{ id: string }>()
    const [type, setType] = useQueryState(
        'type',
        parseAsStringLiteral(['income', 'expense', 'transfer'] as const)
    )
    const { data: transaction, isLoading } = useTransaction(id!)
    const updateTransaction = useUpdateTransaction('/transactions')

    const defaultValues = transaction
        ? {
              type: (type ?? transaction.type) as TransactionFormType,
              account_id: transaction.account.id,
              to_account_id: transaction.toAccount?.id ?? null,
              category_id: transaction.category?.id ?? null,
              amount: transaction.amount,
              to_amount: transaction.toAmount ?? null,
              exchange_rate: transaction.exchangeRate ?? null,
              description: transaction.description ?? '',
              date: transaction.date,
              items: transaction.items?.map(item => ({
                  name: item.name,
                  quantity: item.quantity,
                  price_per_unit: item.pricePerUnit,
              })) ?? [],
              tag_ids: transaction.tags?.map(tag => tag.id) ?? [],
          }
        : undefined

    return (
        <FormPage title="Edit Transaction" backLink="/transactions" isLoading={isLoading}>
            <TransactionForm
                defaultValues={defaultValues}
                editing={transaction ? {
                    type: transaction.type as TransactionFormType,
                    accountId: transaction.account.id,
                    amount: transaction.amount,
                    toAccountId: transaction.toAccount?.id ?? null,
                    toAmount: transaction.toAmount ?? null,
                } : undefined}
                onTypeChange={setType}
                onSubmit={(data) => updateTransaction.mutate({ id: id!, data })}
                isSubmitting={updateTransaction.isPending}
                submitLabel="Save"
            />
        </FormPage>
    )
}
