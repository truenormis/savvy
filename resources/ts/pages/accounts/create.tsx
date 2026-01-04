import { FormPage } from '@/components/shared'
import { AccountForm } from '@/components/features/accounts'
import { useCreateAccount } from '@/hooks'

export default function AccountCreatePage() {
    const createAccount = useCreateAccount('/accounts')

    return (
        <FormPage title="Create Account" backLink="/accounts">
            <AccountForm
                onSubmit={(data) => createAccount.mutate(data)}
                isSubmitting={createAccount.isPending}
                submitLabel="Create"
            />
        </FormPage>
    )
}
