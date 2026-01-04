import { Button } from '@/components/ui/button'
import { ICON_OPTIONS } from '@/constants/categories'

interface IconPickerProps {
    value: string
    onChange: (value: string) => void
    error?: string
}

export function IconPicker({ value, onChange, error }: IconPickerProps) {
    return (
        <div className="space-y-2">
            <label className="text-sm font-medium">Icon</label>
            <div className="flex flex-wrap gap-2">
                {ICON_OPTIONS.map((icon) => (
                    <Button
                        key={icon}
                        type="button"
                        variant={value === icon ? 'default' : 'outline'}
                        size="icon"
                        onClick={() => onChange(icon)}
                    >
                        {icon}
                    </Button>
                ))}
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    )
}
