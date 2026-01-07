import { useParams } from 'react-router-dom'
import { FormPage } from '@/components/shared'
import { UserForm } from '@/components/features/users'
import { useUser, useUpdateUser } from '@/hooks'

export default function UserEditPage() {
    const { id } = useParams<{ id: string }>()
    const { data: user, isLoading } = useUser(id!)
    const updateUser = useUpdateUser('/users')

    return (
        <FormPage title="Edit User" backLink="/users" isLoading={isLoading}>
            <UserForm
                defaultValues={user}
                onSubmit={(data) => updateUser.mutate({ id: id!, data })}
                isSubmitting={updateUser.isPending}
                submitLabel="Save"
                isEdit
            />
        </FormPage>
    )
}
