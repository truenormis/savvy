import { Page, PageHeader, FormPage } from '@/components/shared'
import { TagForm } from '@/components/features/tags'
import { useCreateTag } from '@/hooks'

export default function CreateTagPage() {
    const createTag = useCreateTag('/tags')

    return (
        <Page title="Create Tag">
            <PageHeader
                title="Create Tag"
                description="Add a new tag"
                backLink="/tags"
            />

            <FormPage>
                <TagForm
                    onSubmit={createTag.mutate}
                    isSubmitting={createTag.isPending}
                    submitLabel="Create Tag"
                />
            </FormPage>
        </Page>
    )
}
