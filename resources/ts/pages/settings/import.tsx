import { Page, PageHeader } from '@/components/shared'
import { CsvImportWizard } from '@/components/features/import/CsvImportWizard'

export default function ImportSettingsPage() {
    return (
        <Page title="Import Transactions">
            <PageHeader
                title="Import Transactions"
                description="Import transactions from a CSV file"
            />
            <CsvImportWizard />
        </Page>
    )
}
