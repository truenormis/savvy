import { useParams } from 'react-router-dom'
import { FormPage } from '@/components/shared'
import { CategoryForm } from '@/components/features/categories'
import { useCategory, useUpdateCategory } from '@/hooks'

export default function CategoryEditPage() {
    const { id } = useParams<{ id: string }>()
    const { data: category, isLoading } = useCategory(id!)
    const updateCategory = useUpdateCategory('/categories')

    return (
        <FormPage title="Edit Category" backLink="/categories" isLoading={isLoading}>
            <CategoryForm
                defaultValues={category}
                onSubmit={(data) => updateCategory.mutate({ id: id!, data })}
                isSubmitting={updateCategory.isPending}
                submitLabel="Save"
            />
        </FormPage>
    )
}
