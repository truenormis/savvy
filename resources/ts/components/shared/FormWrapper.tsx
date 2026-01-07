import { useReadOnly } from '@/components/providers/ReadOnlyProvider'
import { cn } from '@/lib/utils'

interface FormWrapperProps {
    children: React.ReactNode
    className?: string
}

export function FormWrapper({ children, className }: FormWrapperProps) {
    const isReadOnly = useReadOnly()

    if (!isReadOnly) {
        return <>{children}</>
    }

    return (
        <div className={cn('pointer-events-none opacity-60', className)}>
            <fieldset disabled className="contents">
                {children}
            </fieldset>
        </div>
    )
}
