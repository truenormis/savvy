import { z } from 'zod'

const conditionSchema = z.object({
    field: z.string().min(1, 'Field is required'),
    op: z.string().min(1, 'Operator is required'),
    value: z.unknown(),
})

const conditionGroupSchema = z.object({
    match: z.enum(['all', 'any']),
    conditions: z.array(conditionSchema).min(1, 'At least one condition is required'),
})

const actionSchema = z.object({
    type: z.string().min(1, 'Action type is required'),
}).passthrough()

export const automationRuleSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    description: z.string().nullable().optional(),
    trigger_type: z.enum([
        'on_transaction_create',
        'on_transaction_update',
    ]),
    priority: z.coerce.number().min(1).max(100).default(50),
    conditions: conditionGroupSchema,
    actions: z.array(actionSchema).min(1, 'At least one action is required'),
    is_active: z.boolean().default(true),
    stop_processing: z.boolean().default(false),
})

export type AutomationRuleSchema = z.infer<typeof automationRuleSchema>
