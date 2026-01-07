import { useParams } from 'react-router-dom'
import { FormPage } from '@/components/shared'
import { RecurringForm } from '@/components/features/recurring'
import { useRecurringById, useUpdateRecurring } from '@/hooks'
import { RecurringFormData } from '@/types'

export default function RecurringEditPage() {
    const { id } = useParams<{ id: string }>()
    const { data: recurring, isLoading } = useRecurringById(id!)
    const updateRecurring = useUpdateRecurring('/recurring')

    const defaultValues: Partial<RecurringFormData> | undefined = recurring ? {
        type: recurring.type,
        account_id: recurring.accountId,
        to_account_id: recurring.toAccountId,
        category_id: recurring.categoryId,
        amount: recurring.amount,
        to_amount: recurring.toAmount,
        description: recurring.description,
        frequency: recurring.frequency,
        interval: recurring.interval,
        day_of_week: recurring.dayOfWeek,
        day_of_month: recurring.dayOfMonth,
        start_date: recurring.startDate,
        end_date: recurring.endDate,
        is_active: recurring.isActive,
        tag_ids: recurring.tags.map(t => t.id),
    } : undefined

    return (
        <FormPage title="Edit Recurring Transaction" backLink="/recurring" isLoading={isLoading}>
            <RecurringForm
                defaultValues={defaultValues}
                onSubmit={(data) => updateRecurring.mutate({ id: id!, data })}
                isSubmitting={updateRecurring.isPending}
                submitLabel="Save"
            />
        </FormPage>
    )
}
