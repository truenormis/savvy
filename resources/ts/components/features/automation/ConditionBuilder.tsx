import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Plus, Trash2 } from 'lucide-react'
import type { Condition, ConditionGroup, ConditionOperator } from '@/types/automation'
import { CONDITION_FIELDS } from '@/types/automation'
import { useAccounts, useCategories, useTags } from '@/hooks'

interface ConditionBuilderProps {
    value: ConditionGroup
    onChange: (value: ConditionGroup) => void
}

const OPERATORS: Record<string, { value: ConditionOperator; label: string }[]> = {
    equals: [
        { value: 'equals', label: 'Equals' },
        { value: 'not_equals', label: 'Not Equals' },
    ],
    in: [
        { value: 'in', label: 'Is One Of' },
        { value: 'not_in', label: 'Is Not One Of' },
    ],
    gt: [
        { value: 'gt', label: 'Greater Than' },
        { value: 'gte', label: 'Greater Than or Equal' },
        { value: 'lt', label: 'Less Than' },
        { value: 'lte', label: 'Less Than or Equal' },
        { value: 'between', label: 'Between' },
    ],
    contains: [
        { value: 'contains', label: 'Contains' },
        { value: 'not_contains', label: 'Does Not Contain' },
        { value: 'starts_with', label: 'Starts With' },
        { value: 'ends_with', label: 'Ends With' },
        { value: 'matches', label: 'Matches Regex' },
    ],
    is_null: [
        { value: 'is_null', label: 'Is Empty' },
        { value: 'is_not_null', label: 'Is Not Empty' },
    ],
    has_any: [
        { value: 'has_any', label: 'Has Any Of' },
        { value: 'has_all', label: 'Has All Of' },
        { value: 'has_none', label: 'Has None Of' },
    ],
}

const TRANSACTION_TYPES = [
    { value: 'income', label: 'Income' },
    { value: 'expense', label: 'Expense' },
    { value: 'transfer', label: 'Transfer' },
]

export function ConditionBuilder({ value, onChange }: ConditionBuilderProps) {
    const { data: accounts } = useAccounts()
    const { data: categories } = useCategories()
    const { data: tags } = useTags()

    const addCondition = () => {
        onChange({
            ...value,
            conditions: [
                ...value.conditions,
                { field: 'type', op: 'equals', value: 'expense' },
            ],
        })
    }

    const updateCondition = (index: number, updates: Partial<Condition>) => {
        const newConditions = value.conditions.map((condition, i) =>
            i === index ? { ...condition, ...updates } : condition
        )
        onChange({ ...value, conditions: newConditions })
    }

    const removeCondition = (index: number) => {
        onChange({
            ...value,
            conditions: value.conditions.filter((_, i) => i !== index),
        })
    }

    const getOperatorsForField = (field: string) => {
        const fieldConfig = CONDITION_FIELDS.find(f => f.value === field)
        if (!fieldConfig) return []

        const operators: { value: ConditionOperator; label: string }[] = []
        fieldConfig.operators.forEach(op => {
            const opGroup = OPERATORS[op]
            if (opGroup) operators.push(...opGroup)
        })
        return operators
    }

    const renderValueInput = (condition: Condition, index: number) => {
        const { field, op } = condition

        if (op === 'is_null' || op === 'is_not_null') {
            return null
        }

        if (field === 'type') {
            if (op === 'in' || op === 'not_in') {
                return (
                    <div className="flex flex-wrap gap-1">
                        {TRANSACTION_TYPES.map(type => (
                            <Button
                                key={type.value}
                                type="button"
                                variant={(condition.value as string[])?.includes(type.value) ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => {
                                    const current = (condition.value as string[]) || []
                                    const newValue = current.includes(type.value)
                                        ? current.filter(v => v !== type.value)
                                        : [...current, type.value]
                                    updateCondition(index, { value: newValue })
                                }}
                            >
                                {type.label}
                            </Button>
                        ))}
                    </div>
                )
            }
            return (
                <Select
                    value={condition.value as string}
                    onValueChange={(val) => updateCondition(index, { value: val })}
                >
                    <SelectTrigger className="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {TRANSACTION_TYPES.map(type => (
                            <SelectItem key={type.value} value={type.value}>
                                {type.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )
        }

        if (field === 'account_id') {
            return (
                <Select
                    value={condition.value ? String(condition.value) : undefined}
                    onValueChange={(val) => updateCondition(index, { value: Number(val) })}
                >
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                        {accounts?.map(account => (
                            <SelectItem key={account.id} value={String(account.id)}>
                                {account.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )
        }

        if (field === 'category_id') {
            return (
                <Select
                    value={condition.value ? String(condition.value) : undefined}
                    onValueChange={(val) => updateCondition(index, { value: Number(val) })}
                >
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="Select category" />
                    </SelectTrigger>
                    <SelectContent>
                        {categories?.map(category => (
                            <SelectItem key={category.id} value={String(category.id)}>
                                {category.icon} {category.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )
        }

        if (field === 'tags') {
            return (
                <div className="flex flex-wrap gap-1">
                    {tags?.map(tag => (
                        <Button
                            key={tag.id}
                            type="button"
                            variant={(condition.value as number[])?.includes(tag.id) ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => {
                                const current = (condition.value as number[]) || []
                                const newValue = current.includes(tag.id)
                                    ? current.filter(v => v !== tag.id)
                                    : [...current, tag.id]
                                updateCondition(index, { value: newValue })
                            }}
                        >
                            #{tag.name}
                        </Button>
                    ))}
                </div>
            )
        }

        if (field === 'amount') {
            if (op === 'between') {
                const [min, max] = (condition.value as [number, number]) || [0, 0]
                return (
                    <div className="flex items-center gap-2">
                        <Input
                            type="number"
                            className="w-24"
                            value={min}
                            onChange={(e) => updateCondition(index, { value: [Number(e.target.value), max] })}
                        />
                        <span className="text-muted-foreground">and</span>
                        <Input
                            type="number"
                            className="w-24"
                            value={max}
                            onChange={(e) => updateCondition(index, { value: [min, Number(e.target.value)] })}
                        />
                    </div>
                )
            }
            return (
                <Input
                    type="number"
                    className="w-32"
                    value={condition.value as number}
                    onChange={(e) => updateCondition(index, { value: Number(e.target.value) })}
                />
            )
        }

        return (
            <Input
                className="w-48"
                value={condition.value as string}
                onChange={(e) => updateCondition(index, { value: e.target.value })}
                placeholder="Enter value..."
            />
        )
    }

    return (
        <div className="space-y-4">
            <Tabs
                value={value.match}
                onValueChange={(match) => onChange({ ...value, match: match as 'all' | 'any' })}
            >
                <TabsList>
                    <TabsTrigger value="all">Match ALL conditions</TabsTrigger>
                    <TabsTrigger value="any">Match ANY condition</TabsTrigger>
                </TabsList>
            </Tabs>

            <div className="space-y-2">
                {value.conditions.map((condition, index) => (
                    <div key={index} className="flex items-center gap-2 p-2 bg-muted/50 rounded">
                        <Select
                            value={condition.field}
                            onValueChange={(field) => {
                                const operators = getOperatorsForField(field)
                                updateCondition(index, {
                                    field,
                                    op: operators[0]?.value || 'equals',
                                    value: null,
                                })
                            }}
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {CONDITION_FIELDS.map(field => (
                                    <SelectItem key={field.value} value={field.value}>
                                        {field.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={condition.op}
                            onValueChange={(op) => updateCondition(index, { op: op as ConditionOperator })}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {getOperatorsForField(condition.field).map(op => (
                                    <SelectItem key={op.value} value={op.value}>
                                        {op.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {renderValueInput(condition, index)}

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => removeCondition(index)}
                        >
                            <Trash2 className="size-4 text-muted-foreground hover:text-destructive" />
                        </Button>
                    </div>
                ))}
            </div>

            <Button type="button" variant="outline" size="sm" onClick={addCondition}>
                <Plus className="size-4 mr-2" />
                Add Condition
            </Button>
        </div>
    )
}
