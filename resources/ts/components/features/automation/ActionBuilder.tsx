import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Plus, Trash2 } from 'lucide-react'
import type { Action, ActionType } from '@/types/automation'
import { ACTION_TYPES } from '@/types/automation'
import { useAccounts, useCategories, useTags } from '@/hooks'

interface ActionBuilderProps {
    value: Action[]
    onChange: (value: Action[]) => void
}

export function ActionBuilder({ value, onChange }: ActionBuilderProps) {
    const { data: accounts } = useAccounts()
    const { data: categories } = useCategories()
    const { data: tags } = useTags()

    const addAction = () => {
        onChange([
            ...value,
            { type: 'set_category' },
        ])
    }

    const updateAction = (index: number, updates: Partial<Action>) => {
        const newActions = value.map((action, i) =>
            i === index ? { ...action, ...updates } : action
        )
        onChange(newActions)
    }

    const removeAction = (index: number) => {
        onChange(value.filter((_, i) => i !== index))
    }

    const renderActionParams = (action: Action, index: number) => {
        switch (action.type) {
            case 'set_category':
                return (
                    <Select
                        value={action.category_id ? String(action.category_id) : undefined}
                        onValueChange={(val) => updateAction(index, { category_id: Number(val) })}
                    >
                        <SelectTrigger className="w-48">
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

            case 'add_tags':
            case 'remove_tags':
                return (
                    <div className="flex flex-wrap gap-1">
                        {tags?.map(tag => (
                            <Button
                                key={tag.id}
                                type="button"
                                variant={(action.tag_ids as number[])?.includes(tag.id) ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => {
                                    const current = (action.tag_ids as number[]) || []
                                    const newValue = current.includes(tag.id)
                                        ? current.filter(v => v !== tag.id)
                                        : [...current, tag.id]
                                    updateAction(index, { tag_ids: newValue })
                                }}
                            >
                                #{tag.name}
                            </Button>
                        ))}
                    </div>
                )

            case 'set_description':
                return (
                    <Input
                        className="w-64"
                        value={(action.template || action.description || '') as string}
                        onChange={(e) => updateAction(index, { template: e.target.value })}
                        placeholder="Description template..."
                    />
                )

            case 'create_transfer':
                return (
                    <div className="flex items-center gap-2">
                        <Select
                            value={action.to_account_id ? String(action.to_account_id) : undefined}
                            onValueChange={(val) => updateAction(index, { to_account_id: Number(val) })}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="To account" />
                            </SelectTrigger>
                            <SelectContent>
                                {accounts?.map(account => (
                                    <SelectItem key={account.id} value={String(account.id)}>
                                        {account.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            className="w-40"
                            value={String(action.amount_formula ?? '')}
                            onChange={(e) => updateAction(index, { amount_formula: e.target.value })}
                            placeholder="Amount or formula"
                        />
                    </div>
                )

            default:
                return null
        }
    }

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                {value.map((action, index) => (
                    <div key={index} className="flex items-center gap-2 p-2 bg-muted/50 rounded">
                        <Select
                            value={action.type}
                            onValueChange={(type) => updateAction(index, { type: type as ActionType })}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ACTION_TYPES.map(actionType => (
                                    <SelectItem key={actionType.value} value={actionType.value}>
                                        {actionType.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {renderActionParams(action, index)}

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => removeAction(index)}
                        >
                            <Trash2 className="size-4 text-muted-foreground hover:text-destructive" />
                        </Button>
                    </div>
                ))}
            </div>

            <Button type="button" variant="outline" size="sm" onClick={addAction}>
                <Plus className="size-4 mr-2" />
                Add Action
            </Button>

            {value.length === 0 && (
                <p className="text-sm text-muted-foreground text-center py-4 border rounded-lg border-dashed">
                    No actions. Click "Add Action" to create one.
                </p>
            )}
        </div>
    )
}
