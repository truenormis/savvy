import { useParams } from 'react-router-dom'
import { FormPage } from '@/components/shared'
import { AutomationRuleForm } from '@/components/features/automation'
import { useAutomationRuleById, useUpdateAutomationRule } from '@/hooks/use-automation'
import { Skeleton } from '@/components/ui/skeleton'

export default function EditAutomationPage() {
    const { id } = useParams<{ id: string }>()
    const { data: rule, isLoading } = useAutomationRuleById(id!)
    const updateRule = useUpdateAutomationRule('/automation')

    if (isLoading) {
        return (
            <FormPage title="Edit Automation Rule" backLink="/automation">
                <div className="space-y-4">
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-32 w-full" />
                </div>
            </FormPage>
        )
    }

    if (!rule) {
        return (
            <FormPage title="Edit Automation Rule" backLink="/automation">
                <p className="text-muted-foreground">Rule not found</p>
            </FormPage>
        )
    }

    return (
        <FormPage
            title="Edit Automation Rule"
            description={rule.name}
            backLink="/automation"
        >
            <AutomationRuleForm
                defaultValues={{
                    name: rule.name,
                    description: rule.description,
                    trigger_type: rule.trigger_type,
                    priority: rule.priority,
                    conditions: rule.conditions,
                    actions: rule.actions,
                    is_active: rule.is_active,
                    stop_processing: rule.stop_processing,
                }}
                onSubmit={(data) => updateRule.mutate({ id: id!, data })}
                isSubmitting={updateRule.isPending}
                submitLabel="Update Rule"
            />
        </FormPage>
    )
}
