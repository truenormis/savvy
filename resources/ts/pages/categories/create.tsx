import { FormPage } from '@/components/shared'
import { CategoryForm } from '@/components/features/categories'
import { useCreateCategory } from '@/hooks'

export default function CategoryCreatePage() {
    const createCategory = useCreateCategory('/categories')

    return (
        <FormPage title="Create Category" backLink="/categories">
            <CategoryForm
                onSubmit={(data) => createCategory.mutate(data)}
                isSubmitting={createCategory.isPending}
                submitLabel="Create"
            />
        </FormPage>
    )
}
