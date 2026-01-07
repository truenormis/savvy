import { useState } from 'react'
import { Eye, X } from 'lucide-react'
import { useReadOnly } from '@/components/providers/ReadOnlyProvider'

const STORAGE_KEY = 'savvy-readonly-banner-hidden'

export function ReadOnlyBanner() {
    const isReadOnly = useReadOnly()
    const [isHidden, setIsHidden] = useState(() => {
        return localStorage.getItem(STORAGE_KEY) === 'true'
    })

    const handleClose = () => {
        setIsHidden(true)
        localStorage.setItem(STORAGE_KEY, 'true')
    }

    if (!isReadOnly || isHidden) return null

    return (
        <div className="bg-amber-500 text-amber-950 px-4 py-2 text-sm font-medium flex items-center justify-center gap-2 relative">
            <Eye className="size-4" />
            <span>You are in read-only mode. You can view all data but cannot make any changes.</span>
            <button
                onClick={handleClose}
                className="absolute right-2 p-1 hover:bg-amber-600/20 rounded transition-colors"
                aria-label="Close banner"
            >
                <X className="size-4" />
            </button>
        </div>
    )
}
