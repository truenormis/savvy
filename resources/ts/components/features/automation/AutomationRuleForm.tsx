import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import {
    Form,
    FormField,
    FormItem,
    FormLabel,
    FormControl,
    FormMessage,
    FormDescription,
} from '@/components/ui/form'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip'
import { HelpCircle } from 'lucide-react'
import { automationRuleSchema, type AutomationRuleSchema } from '@/schemas/automation'
import { useAutomationTriggers } from '@/hooks/use-automation'
import { ConditionBuilder } from './ConditionBuilder'
import { ActionBuilder } from './ActionBuilder'
import type { AutomationRuleFormData, TriggerType } from '@/types/automation'
import { FormWrapper } from '@/components/shared/FormWrapper'

interface AutomationRuleFormProps {
    defaultValues?: Partial<AutomationRuleFormData>
    onSubmit: (data: AutomationRuleFormData) => void
    isSubmitting?: boolean
    submitLabel?: string
}

export function AutomationRuleForm({
    defaultValues,
    onSubmit,
    isSubmitting,
    submitLabel = 'Save',
}: AutomationRuleFormProps) {
    const { data: triggers } = useAutomationTriggers()

    const form = useForm<AutomationRuleSchema>({
        resolver: zodResolver(automationRuleSchema),
        defaultValues: {
            name: '',
            description: null,
            trigger_type: 'on_transaction_create',
            priority: 50,
            conditions: { match: 'all', conditions: [] },
            actions: [],
            is_active: true,
            stop_processing: false,
            ...defaultValues,
        },
    })

    const handleSubmit = (data: AutomationRuleSchema) => {
        onSubmit(data as AutomationRuleFormData)
    }

    return (
        <FormWrapper>
        <Form {...form}>
            <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6">
                <div className="grid grid-cols-2 gap-4">
                    <FormField
                        control={form.control}
                        name="name"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Name</FormLabel>
                                <FormControl>
                                    <Input placeholder="e.g., Auto-categorize groceries" {...field} />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        )}
                    />

                    <FormField
                        control={form.control}
                        name="priority"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Priority</FormLabel>
                                <FormControl>
                                    <Input type="number" min={1} max={100} {...field} />
                                </FormControl>
                                <FormDescription>Lower number = higher priority (1-100)</FormDescription>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                </div>

                <FormField
                    control={form.control}
                    name="description"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Description</FormLabel>
                            <FormControl>
                                <Textarea
                                    placeholder="Describe what this rule does..."
                                    className="resize-none h-20"
                                    {...field}
                                    value={field.value ?? ''}
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="trigger_type"
                    render={({ field }) => {
                        const selectedTrigger = triggers?.find(t => t.value === field.value)
                        return (
                            <FormItem>
                                <FormLabel>Trigger</FormLabel>
                                <div className="flex items-center gap-2">
                                    <Select onValueChange={field.onChange} value={field.value}>
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select trigger" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            {triggers?.map((trigger) => (
                                                <SelectItem key={trigger.value} value={trigger.value}>
                                                    {trigger.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {selectedTrigger && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <HelpCircle className="size-4 text-muted-foreground cursor-help" />
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{selectedTrigger.description}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </div>
                                <FormMessage />
                            </FormItem>
                        )
                    }}
                />

                <div className="space-y-4 p-4 border rounded-lg">
                    <h3 className="font-medium">Conditions</h3>
                    <FormField
                        control={form.control}
                        name="conditions"
                        render={({ field }) => (
                            <FormItem>
                                <ConditionBuilder
                                    value={field.value}
                                    onChange={field.onChange}
                                />
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                </div>

                <div className="space-y-4 p-4 border rounded-lg">
                    <h3 className="font-medium">Actions</h3>
                    <FormField
                        control={form.control}
                        name="actions"
                        render={({ field }) => (
                            <FormItem>
                                <ActionBuilder
                                    value={field.value}
                                    onChange={field.onChange}
                                />
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                </div>

                <div className="flex items-center gap-6">
                    <FormField
                        control={form.control}
                        name="is_active"
                        render={({ field }) => (
                            <FormItem className="flex items-center gap-2 space-y-0">
                                <FormControl>
                                    <Switch
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                    />
                                </FormControl>
                                <FormLabel className="cursor-pointer">Active</FormLabel>
                            </FormItem>
                        )}
                    />

                    <FormField
                        control={form.control}
                        name="stop_processing"
                        render={({ field }) => (
                            <FormItem className="flex items-center gap-2 space-y-0">
                                <FormControl>
                                    <Switch
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                    />
                                </FormControl>
                                <FormLabel className="cursor-pointer">Stop processing other rules</FormLabel>
                            </FormItem>
                        )}
                    />
                </div>

                <Button type="submit" disabled={isSubmitting} className="w-full">
                    {isSubmitting ? 'Saving...' : submitLabel}
                </Button>
            </form>
        </Form>
        </FormWrapper>
    )
}
