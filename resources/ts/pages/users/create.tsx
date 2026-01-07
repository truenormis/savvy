import { FormPage } from '@/components/shared'
import { UserForm } from '@/components/features/users'
import { useCreateUser } from '@/hooks'

export default function UserCreatePage() {
    const createUser = useCreateUser('/users')

    return (
        <FormPage title="Create User" backLink="/users">
            <UserForm
                onSubmit={(data) => createUser.mutate(data)}
                isSubmitting={createUser.isPending}
                submitLabel="Create"
            />
        </FormPage>
    )
}
