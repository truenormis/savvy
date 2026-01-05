import { EmojiPicker } from '@/components/ui/emoji-picker'

interface IconPickerProps {
    value: string
    onChange: (value: string) => void
    error?: string
}

export function IconPicker({ value, onChange, error }: IconPickerProps) {
    return (
        <div className="space-y-2">
            <label className="text-sm font-medium">Icon</label>
            <EmojiPicker value={value} onChange={onChange} />
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    )
}
