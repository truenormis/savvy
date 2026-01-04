import { useParams } from 'react-router-dom'
import { FormPage } from '@/components/shared'
import { CurrencyForm } from '@/components/features/currencies'
import { useCurrency, useUpdateCurrency } from '@/hooks'

export default function CurrencyEditPage() {
    const { id } = useParams<{ id: string }>()
    const { data: currency, isLoading } = useCurrency(id!)
    const updateCurrency = useUpdateCurrency('/currencies')

    return (
        <FormPage title="Edit Currency" backLink="/currencies" isLoading={isLoading}>
            <CurrencyForm
                defaultValues={currency}
                onSubmit={(data) => updateCurrency.mutate({ id: id!, data })}
                isSubmitting={updateCurrency.isPending}
                submitLabel="Save"
                isEditing
            />
        </FormPage>
    )
}
