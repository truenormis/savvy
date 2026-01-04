import { Button } from '@/components/ui/button'
import { Link } from 'react-router-dom'
import { Plus, ArrowLeft } from 'lucide-react'
import { Separator } from '@/components/ui/separator'

interface Props {
    title: string
    description?: string
    createLink?: string
    createLabel?: string
    backLink?: string
    actions?: React.ReactNode
}

export function PageHeader({
                               title,
                               description,
                               createLink,
                               createLabel = 'Create',
                               backLink,
                               actions
                           }: Props) {
    return (
        <div className="mb-8">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                    {backLink && (
                        <Button variant="ghost" size="icon" asChild>
                            <Link to={backLink}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                    )}
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">{title}</h1>
                        {description && (
                            <p className="text-muted-foreground mt-1">{description}</p>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {actions}
                    {createLink && (
                        <Button asChild>
                            <Link to={createLink}>
                                <Plus className="mr-2 h-4 w-4" />
                                {createLabel}
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
            <Separator className="mt-6" />
        </div>
    )
}
