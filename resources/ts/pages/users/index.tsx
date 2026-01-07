import { ListPage } from '@/components/shared'
import { createUserColumns } from '@/components/features/users'
import { useUsers, useDeleteUser } from '@/hooks'
import { useUser as useCurrentUser } from '@/stores/auth'

export default function UsersPage() {
    const currentUser = useCurrentUser()
    const { data: users, isLoading } = useUsers()
    const deleteUser = useDeleteUser()

    const columns = createUserColumns(
        (id) => deleteUser.mutate(id),
        currentUser?.id
    )

    return (
        <ListPage
            title="Users"
            description="Manage user accounts"
            createLink="/users/create"
            createLabel="New User"
            data={users ?? []}
            columns={columns}
            isLoading={isLoading}
        />
    )
}
