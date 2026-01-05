import { useState } from 'react'
import { Page, PageHeader } from '@/components/shared'
import { Card, CardContent } from '@/components/ui/card'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Activity } from 'lucide-react'
import { FiltersBar } from './components'
import { OverviewTab, CashFlowTab, ExpensesTab, IncomeTab, NetWorthTab } from './tabs'
import { DEFAULT_FILTERS, TABS, type ReportFilters, type ReportTab } from './types'

export default function ReportsPage() {
    const [filters, setFilters] = useState<ReportFilters>(DEFAULT_FILTERS)
    const [activeTab, setActiveTab] = useState<ReportTab>('overview')

    const updateFilter = <K extends keyof ReportFilters>(key: K, value: ReportFilters[K]) => {
        setFilters(f => ({ ...f, [key]: value }))
    }

    const toggleArrayFilter = (key: 'accountIds' | 'categoryIds' | 'tagIds', id: number) => {
        setFilters(f => {
            const current = f[key]
            const newIds = current.includes(id)
                ? current.filter(i => i !== id)
                : [...current, id]
            return { ...f, [key]: newIds }
        })
    }

    const resetFilters = () => {
        setFilters(DEFAULT_FILTERS)
    }

    return (
        <Page title="Reports">
            <PageHeader
                title="Reports"
                description="Analyze your finances across different dimensions"
            />

            {/* Global Filters Bar */}
            <FiltersBar
                filters={filters}
                onFilterChange={updateFilter}
                onToggleArrayFilter={toggleArrayFilter}
                onReset={resetFilters}
            />

            {/* Tab Navigation */}
            <Tabs value={activeTab} onValueChange={(val) => setActiveTab(val as ReportTab)} className="mb-6">
                <TabsList>
                    {TABS.map(tab => (
                        <TabsTrigger key={tab.value} value={tab.value}>
                            {tab.label}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </Tabs>

            {/* Tab Content */}
            {activeTab === 'overview' && (
                <OverviewTab filters={filters} />
            )}

            {activeTab === 'cashflow' && (
                <CashFlowTab filters={filters} />
            )}

            {activeTab === 'expenses' && (
                <ExpensesTab filters={filters} />
            )}

            {activeTab === 'income' && (
                <IncomeTab filters={filters} />
            )}

            {activeTab === 'networth' && (
                <NetWorthTab filters={filters} />
            )}
        </Page>
    )
}
