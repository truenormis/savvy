import { ListPage } from '@/components/shared'
import { createCurrencyColumns } from '@/components/features/currencies'
import { useCurrencies, useDeleteCurrency, useSetBaseCurrency } from '@/hooks'

export default function CurrenciesPage() {
    const { data: currencies, isLoading } = useCurrencies()
    const deleteCurrency = useDeleteCurrency()
    const setBaseCurrency = useSetBaseCurrency()

    const columns = createCurrencyColumns({
        onDelete: (id) => deleteCurrency.mutate(id),
        onSetBase: (id) => setBaseCurrency.mutate(id),
        isSettingBase: setBaseCurrency.isPending,
    })

    return (
        <ListPage
            title="Currencies"
            description="Manage currencies and exchange rates"
            createLink="/currencies/create"
            createLabel="New Currency"
            data={currencies ?? []}
            columns={columns}
            isLoading={isLoading}
        />
    )
}
