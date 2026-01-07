import { ListPage } from '@/components/shared'
import { createCurrencyColumns } from '@/components/features/currencies'
import { useCurrencies, useDeleteCurrency, useSetBaseCurrency } from '@/hooks'
import { useReadOnly } from '@/components/providers/ReadOnlyProvider'

export default function CurrenciesPage() {
    const { data: currencies, isLoading } = useCurrencies()
    const deleteCurrency = useDeleteCurrency()
    const setBaseCurrency = useSetBaseCurrency()
    const isReadOnly = useReadOnly()

    const columns = createCurrencyColumns({
        onDelete: (id) => deleteCurrency.mutate(id),
        onSetBase: (id) => setBaseCurrency.mutate(id),
        isSettingBase: setBaseCurrency.isPending,
        currencyCount: currencies?.length ?? 0,
        isReadOnly,
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
