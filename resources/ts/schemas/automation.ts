import { z } from 'zod'
import { numeric } from './numeric'

const conditionOperatorSchema = z.enum([
    'equals', 'not_equals', 'in', 'not_in', 'gt', 'gte', 'lt', 'lte', 'between',
    'contains', 'not_contains', 'starts_with', 'ends_with', 'matches',
    'is_null', 'is_not_null', 'has_any', 'has_all', 'has_none',
], { error: 'Operator is required' })

const actionTypeSchema = z.enum([
    'set_category', 'add_tags', 'remove_tags', 'set_description', 'create_transfer',
], { error: 'Action type is required' })

const conditionSchema = z.object({
    field: z.string().min(1, 'Field is required'),
    op: conditionOperatorSchema,
    value: z.unknown(),
})

const conditionGroupSchema = z.object({
    match: z.enum(['all', 'any']),
    conditions: z.array(conditionSchema).min(1, 'At least one condition is required'),
})

const actionSchema = z.object({
    type: actionTypeSchema,
}).loose()

export const automationRuleSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    description: z.string().nullable().optional(),
    trigger_type: z.enum([
        'on_transaction_create',
        'on_transaction_update',
    ]),
    priority: numeric(z.number().min(1).max(100)).default(50),
    conditions: conditionGroupSchema,
    actions: z.array(actionSchema).min(1, 'At least one action is required'),
    is_active: z.boolean().default(true),
    stop_processing: z.boolean().default(false),
})

export type AutomationRuleSchema = z.output<typeof automationRuleSchema>
export type AutomationRuleInput = z.input<typeof automationRuleSchema>
