import { FormPage } from '@/components/shared'
import { CurrencyForm } from '@/components/features/currencies'
import { useCreateCurrency } from '@/hooks'

export default function CurrencyCreatePage() {
    const createCurrency = useCreateCurrency('/currencies')

    return (
        <FormPage title="Create Currency" backLink="/currencies">
            <CurrencyForm
                onSubmit={(data) => createCurrency.mutate(data)}
                isSubmitting={createCurrency.isPending}
                submitLabel="Create"
            />
        </FormPage>
    )
}
