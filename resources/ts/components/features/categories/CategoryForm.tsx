import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Form,
    FormField,
    FormItem,
    FormLabel,
    FormControl,
    FormMessage,
} from '@/components/ui/form'
import { categorySchema, CategoryFormData } from '@/schemas'
import { TypeSelector } from './TypeSelector'
import { IconPicker } from './IconPicker'
import { ColorPicker } from './ColorPicker'
import { CategoryPreview } from './CategoryPreview'
import { FormWrapper } from '@/components/shared/FormWrapper'

interface CategoryFormProps {
    defaultValues?: Partial<CategoryFormData>
    onSubmit: (data: CategoryFormData) => void
    isSubmitting?: boolean
    submitLabel?: string
}

export function CategoryForm({
    defaultValues,
    onSubmit,
    isSubmitting,
    submitLabel = 'Save',
}: CategoryFormProps) {
    const form = useForm<CategoryFormData>({
        resolver: zodResolver(categorySchema),
        defaultValues: {
            name: '',
            type: 'expense',
            icon: '🏠',
            color: '#3B82F6',
            ...defaultValues,
        },
    })

    const watchedValues = form.watch()

    return (
        <FormWrapper>
        <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="max-w-md space-y-4">
                <FormField
                    control={form.control}
                    name="name"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Name</FormLabel>
                            <FormControl>
                                <Input placeholder="Category name" {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="type"
                    render={({ field }) => (
                        <FormItem>
                            <TypeSelector
                                value={field.value}
                                onChange={field.onChange}
                                error={form.formState.errors.type?.message}
                            />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="icon"
                    render={({ field }) => (
                        <FormItem>
                            <IconPicker
                                value={field.value}
                                onChange={field.onChange}
                                error={form.formState.errors.icon?.message}
                            />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="color"
                    render={({ field }) => (
                        <FormItem>
                            <ColorPicker
                                value={field.value}
                                onChange={field.onChange}
                                error={form.formState.errors.color?.message}
                            />
                        </FormItem>
                    )}
                />

                <CategoryPreview
                    name={watchedValues.name}
                    icon={watchedValues.icon}
                    color={watchedValues.color}
                />

                <Button type="submit" disabled={isSubmitting} className="w-full">
                    {isSubmitting ? 'Saving...' : submitLabel}
                </Button>
            </form>
        </Form>
        </FormWrapper>
    )
}
