import { ListPage } from '@/components/shared'
import { createAutomationColumns } from '@/components/features/automation'
import { useAutomationRules, useDeleteAutomationRule, useToggleAutomationRule } from '@/hooks/use-automation'
import { useReadOnly } from '@/components/providers/ReadOnlyProvider'

export default function AutomationPage() {
    const { data: rules, isLoading } = useAutomationRules()
    const deleteRule = useDeleteAutomationRule()
    const toggleRule = useToggleAutomationRule()
    const isReadOnly = useReadOnly()

    const columns = createAutomationColumns({
        onDelete: (id) => deleteRule.mutate(id),
        onToggle: (id) => toggleRule.mutate(id),
        isReadOnly,
    })

    return (
        <ListPage
            title="Automation Rules"
            description="Create rules to automatically process transactions"
            createLink="/automation/create"
            createLabel="New Rule"
            data={rules ?? []}
            columns={columns}
            isLoading={isLoading}
        />
    )
}
