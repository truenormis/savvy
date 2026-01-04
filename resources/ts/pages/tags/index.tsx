import { Page, PageHeader, DataTable } from '@/components/shared'
import { createTagColumns } from '@/components/features/tags'
import { useTags, useDeleteTag } from '@/hooks'
import { Button } from '@/components/ui/button'
import { Link } from 'react-router-dom'
import { Plus, Hash } from 'lucide-react'

export default function TagsPage() {
    const { data: tags, isLoading } = useTags()
    const deleteTag = useDeleteTag()

    const columns = createTagColumns((id) => deleteTag.mutate(id))

    return (
        <Page title="Tags">
            <PageHeader
                title="Tags"
                description="Organize transactions with tags"
                createLink="/tags/create"
                createLabel="New Tag"
            />

            <DataTable
                data={tags ?? []}
                columns={columns}
                isLoading={isLoading}
                emptyTitle="No tags found"
                emptyDescription="Create your first tag to organize transactions"
                emptyAction={
                    <Button asChild>
                        <Link to="/tags/create">
                            <Plus className="size-4" />
                            New Tag
                        </Link>
                    </Button>
                }
            />
        </Page>
    )
}
