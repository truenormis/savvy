import { FormPage } from '@/components/shared'
import { RecurringForm } from '@/components/features/recurring'
import { useCreateRecurring } from '@/hooks'

export default function RecurringCreatePage() {
    const createRecurring = useCreateRecurring('/recurring')

    return (
        <FormPage title="New Recurring Transaction" backLink="/recurring">
            <RecurringForm
                onSubmit={(data) => createRecurring.mutate(data)}
                isSubmitting={createRecurring.isPending}
                submitLabel="Create"
            />
        </FormPage>
    )
}
