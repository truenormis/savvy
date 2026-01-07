import { ListPage } from '@/components/shared'
import { createRecurringColumns } from '@/components/features/recurring'
import { useRecurring, useDeleteRecurring, useSkipRecurring } from '@/hooks'

export default function RecurringPage() {
    const { data: recurring, isLoading } = useRecurring()
    const deleteRecurring = useDeleteRecurring()
    const skipRecurring = useSkipRecurring()

    const columns = createRecurringColumns({
        onDelete: (id) => deleteRecurring.mutate(id),
        onSkip: (id) => skipRecurring.mutate(id),
    })

    return (
        <ListPage
            title="Recurring Transactions"
            description="Manage automatically repeating transactions"
            createLink="/recurring/create"
            createLabel="New Recurring"
            data={recurring ?? []}
            columns={columns}
            isLoading={isLoading}
        />
    )
}
