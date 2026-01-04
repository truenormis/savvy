import { Button } from '@/components/ui/button'
import { CATEGORY_TYPE_OPTIONS } from '@/constants/categories'
import { CategoryType } from '@/types'
import { cn } from '@/lib/utils'

interface TypeSelectorProps {
    value: CategoryType
    onChange: (value: CategoryType) => void
    error?: string
}

export function TypeSelector({ value, onChange, error }: TypeSelectorProps) {
    return (
        <div className="space-y-2">
            <label className="text-sm font-medium">Type</label>
            <div className="flex gap-2">
                {CATEGORY_TYPE_OPTIONS.map((option) => (
                    <Button
                        key={option.value}
                        type="button"
                        variant={value === option.value ? 'default' : 'outline'}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            value === option.value &&
                                (option.value === 'income'
                                    ? 'bg-green-600 hover:bg-green-700'
                                    : 'bg-red-600 hover:bg-red-700')
                        )}
                    >
                        {option.label}
                    </Button>
                ))}
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    )
}
