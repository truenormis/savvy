import { cn } from '@/lib/utils'
import { COLOR_OPTIONS } from '@/constants/categories'

interface ColorPickerProps {
    value: string
    onChange: (value: string) => void
    error?: string
}

export function ColorPicker({ value, onChange, error }: ColorPickerProps) {
    return (
        <div className="space-y-2">
            <label className="text-sm font-medium">Color</label>
            <div className="flex flex-wrap gap-2">
                {COLOR_OPTIONS.map((color) => (
                    <button
                        key={color}
                        type="button"
                        className={cn(
                            'w-8 h-8 rounded-full border-2 transition-transform',
                            value === color
                                ? 'border-foreground scale-110'
                                : 'border-transparent'
                        )}
                        style={{ backgroundColor: color }}
                        onClick={() => onChange(color)}
                        aria-label={`Select color ${color}`}
                    />
                ))}
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    )
}
