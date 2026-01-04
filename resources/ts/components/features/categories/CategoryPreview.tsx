interface CategoryPreviewProps {
    name: string
    icon: string
    color: string
}

export function CategoryPreview({ name, icon, color }: CategoryPreviewProps) {
    return (
        <div className="p-4 border rounded-lg bg-muted/50">
            <p className="text-sm text-muted-foreground mb-2">Preview:</p>
            <div className="flex items-center gap-2">
                <span
                    className="w-10 h-10 rounded-lg flex items-center justify-center text-white text-xl"
                    style={{ backgroundColor: color }}
                >
                    {icon}
                </span>
                <span className="font-medium">{name || 'Category name'}</span>
            </div>
        </div>
    )
}
