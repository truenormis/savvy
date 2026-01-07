export type TriggerType =
    | 'on_transaction_create'
    | 'on_transaction_update'

export type ConditionOperator =
    | 'equals'
    | 'not_equals'
    | 'in'
    | 'not_in'
    | 'gt'
    | 'gte'
    | 'lt'
    | 'lte'
    | 'between'
    | 'contains'
    | 'not_contains'
    | 'starts_with'
    | 'ends_with'
    | 'matches'
    | 'is_null'
    | 'is_not_null'
    | 'has_any'
    | 'has_all'
    | 'has_none'

export type ActionType =
    | 'set_category'
    | 'add_tags'
    | 'remove_tags'
    | 'set_description'
    | 'create_transfer'

export interface Condition {
    field: string
    op: ConditionOperator
    value: unknown
}

export interface ConditionGroup {
    match: 'all' | 'any'
    conditions: Condition[]
}

export interface Action {
    type: ActionType
    [key: string]: unknown
}

export interface AutomationRule {
    id: number
    name: string
    description: string | null
    trigger_type: TriggerType
    trigger_label: string
    priority: number
    conditions: ConditionGroup
    actions: Action[]
    is_active: boolean
    stop_processing: boolean
    runs_count: number
    last_run_at: string | null
    created_at: string
    updated_at: string
}

export interface AutomationRuleLog {
    id: number
    rule_id: number
    trigger_entity_type: string | null
    trigger_entity_id: number | null
    actions_executed: Array<{ type: string; result: unknown }> | null
    status: 'success' | 'error' | 'skipped'
    error_message: string | null
    created_at: string
}

export interface TriggerOption {
    value: TriggerType
    label: string
    description: string
}

export interface AutomationRuleFormData {
    name: string
    description: string | null
    trigger_type: TriggerType
    priority: number
    conditions: ConditionGroup
    actions: Action[]
    is_active: boolean
    stop_processing: boolean
}

export const CONDITION_FIELDS = [
    { value: 'type', label: 'Transaction Type', operators: ['equals', 'in'] },
    { value: 'amount', label: 'Amount', operators: ['equals', 'gt', 'gte', 'lt', 'lte', 'between'] },
    { value: 'description', label: 'Description', operators: ['contains', 'not_contains', 'starts_with', 'ends_with', 'matches'] },
    { value: 'account_id', label: 'Account', operators: ['equals', 'in'] },
    { value: 'category_id', label: 'Category', operators: ['equals', 'in', 'is_null', 'is_not_null'] },
    { value: 'tags', label: 'Tags', operators: ['has_any', 'has_all', 'has_none'] },
] as const

export const ACTION_TYPES = [
    { value: 'set_category', label: 'Set Category', description: 'Change the transaction category' },
    { value: 'add_tags', label: 'Add Tags', description: 'Add tags to the transaction' },
    { value: 'remove_tags', label: 'Remove Tags', description: 'Remove tags from the transaction' },
    { value: 'set_description', label: 'Set Description', description: 'Change the transaction description' },
    { value: 'create_transfer', label: 'Create Transfer', description: 'Create an automatic transfer' },
] as const
