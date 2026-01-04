import { Page } from './Page'
import { PageHeader } from './PageHeader'
import { Skeleton } from '@/components/ui/skeleton'

interface FormPageProps {
    title: string
    backLink?: string
    isLoading?: boolean
    children: React.ReactNode
}

export function FormPage({ title, backLink, isLoading, children }: FormPageProps) {
    if (isLoading) {
        return (
            <Page title={title}>
                <div className="space-y-4">
                    <Skeleton className="h-8 w-48" />
                    <div className="max-w-md space-y-4">
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-24 w-full" />
                    </div>
                </div>
            </Page>
        )
    }

    return (
        <Page title={title}>
            <PageHeader title={title} backLink={backLink} />
            {children}
        </Page>
    )
}
