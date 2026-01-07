import { ListPage } from '@/components/shared'
import { createAutomationColumns } from '@/components/features/automation'
import { useAutomationRules, useDeleteAutomationRule, useToggleAutomationRule } from '@/hooks/use-automation'

export default function AutomationPage() {
    const { data: rules, isLoading } = useAutomationRules()
    const deleteRule = useDeleteAutomationRule()
    const toggleRule = useToggleAutomationRule()

    const columns = createAutomationColumns({
        onDelete: (id) => deleteRule.mutate(id),
        onToggle: (id) => toggleRule.mutate(id),
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
