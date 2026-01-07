import { useMemo } from 'react'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { FormControl } from '@/components/ui/form'
import { useCategories } from '@/hooks'

interface CategorySelectProps {
    value?: number | null
    onChange: (value: number) => void
    type: 'income' | 'expense'
    placeholder?: string
    disabled?: boolean
    sortByPopularity?: boolean
}

export function CategorySelect({
    value,
    onChange,
    type,
    placeholder = 'Select category',
    disabled,
    sortByPopularity = true,
}: CategorySelectProps) {
    const { data: categories } = useCategories()

    const filteredCategories = useMemo(() => {
        const filtered = categories?.filter(c => c.type === type) ?? []
        if (sortByPopularity) {
            return filtered.sort((a, b) => (b.transactionsCount ?? 0) - (a.transactionsCount ?? 0))
        }
        return filtered
    }, [categories, type, sortByPopularity])

    return (
        <Select
            onValueChange={(val) => onChange(Number(val))}
            value={value ? value.toString() : undefined}
            disabled={disabled}
        >
            <FormControl>
                <SelectTrigger>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
            </FormControl>
            <SelectContent>
                {filteredCategories.map((category) => (
                    <SelectItem
                        key={category.id}
                        value={category.id.toString()}
                    >
                        <div className="flex items-center gap-2">
                            <span
                                className="w-5 h-5 rounded flex items-center justify-center text-xs text-white"
                                style={{ backgroundColor: category.color }}
                            >
                                {category.icon}
                            </span>
                            <span>{category.name}</span>
                        </div>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    )
}
