import { ListPage } from '@/components/shared'
import { createCategoryColumns } from '@/components/features/categories'
import { useCategories, useDeleteCategory } from '@/hooks'

export default function CategoriesPage() {
    const { data: categories, isLoading } = useCategories()
    const deleteCategory = useDeleteCategory()

    const columns = createCategoryColumns((id) => deleteCategory.mutate(id))

    return (
        <ListPage
            title="Categories"
            description="Manage income and expense categories"
            createLink="/categories/create"
            createLabel="New Category"
            data={categories ?? []}
            columns={columns}
            isLoading={isLoading}
        />
    )
}
