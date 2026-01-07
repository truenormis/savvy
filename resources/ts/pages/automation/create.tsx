import { FormPage } from '@/components/shared'
import { AutomationRuleForm } from '@/components/features/automation'
import { useCreateAutomationRule } from '@/hooks/use-automation'

export default function CreateAutomationPage() {
    const createRule = useCreateAutomationRule('/automation')

    return (
        <FormPage
            title="Create Automation Rule"
            description="Set up conditions and actions for automatic processing"
            backLink="/automation"
        >
            <AutomationRuleForm
                onSubmit={createRule.mutate}
                isSubmitting={createRule.isPending}
                submitLabel="Create Rule"
            />
        </FormPage>
    )
}
