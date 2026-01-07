import { useMemo } from 'react'
import { ListPage } from '@/components/shared'
import { createCategoryColumns } from '@/components/features/categories'
import { useCategories, useDeleteCategory } from '@/hooks'

export default function CategoriesPage() {
    const { data: categories, isLoading } = useCategories()
    const deleteCategory = useDeleteCategory()

    const typeCounts = useMemo(() => ({
        income: categories?.filter(c => c.type === 'income').length ?? 0,
        expense: categories?.filter(c => c.type === 'expense').length ?? 0,
    }), [categories])

    const columns = createCategoryColumns(
        (id) => deleteCategory.mutate(id),
        typeCounts
    )

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
