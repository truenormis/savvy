import { useMemo } from 'react'
import { ListPage } from '@/components/shared'
import { createCategoryColumns } from '@/components/features/categories'
import { useCategories, useDeleteCategory } from '@/hooks'
import { useReadOnly } from '@/components/providers/ReadOnlyProvider'

export default function CategoriesPage() {
    const { data: categories, isLoading } = useCategories()
    const deleteCategory = useDeleteCategory()
    const isReadOnly = useReadOnly()

    const typeCounts = useMemo(() => ({
        income: categories?.filter(c => c.type === 'income').length ?? 0,
        expense: categories?.filter(c => c.type === 'expense').length ?? 0,
    }), [categories])

    const columns = createCategoryColumns(
        (id) => deleteCategory.mutate(id),
        typeCounts,
        isReadOnly
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
