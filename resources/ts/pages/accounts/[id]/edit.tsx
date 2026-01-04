import { useParams } from 'react-router-dom'
import { FormPage } from '@/components/shared'
import { AccountForm } from '@/components/features/accounts'
import { useAccount, useUpdateAccount } from '@/hooks'

export default function AccountEditPage() {
    const { id } = useParams<{ id: string }>()
    const { data: account, isLoading } = useAccount(id!)
    const updateAccount = useUpdateAccount('/accounts')

    const defaultValues = account
        ? {
              name: account.name,
              type: account.type,
              currency_id: account.currencyId,
              initial_balance: account.initialBalance,
              is_active: account.isActive,
          }
        : undefined

    return (
        <FormPage title="Edit Account" backLink="/accounts" isLoading={isLoading}>
            <AccountForm
                defaultValues={defaultValues}
                onSubmit={(data) => updateAccount.mutate({ id: id!, data })}
                isSubmitting={updateAccount.isPending}
                submitLabel="Save"
            />
        </FormPage>
    )
}
