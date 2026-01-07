import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Form,
    FormField,
    FormItem,
    FormLabel,
    FormControl,
    FormMessage,
    FormDescription,
} from '@/components/ui/form'
import { FormWrapper } from '@/components/shared/FormWrapper'

const tagSchema = z.object({
    name: z.string()
        .min(1, 'Name is required')
        .max(50, 'Name is too long')
        .regex(/^[a-zA-Zа-яА-ЯёЁ0-9_-]+$/, 'Only letters, numbers, _ and - allowed'),
})

type TagFormValues = z.infer<typeof tagSchema>

interface TagFormProps {
    defaultValues?: Partial<TagFormValues>
    onSubmit: (data: TagFormValues) => void
    isSubmitting?: boolean
    submitLabel?: string
}

export function TagForm({
    defaultValues,
    onSubmit,
    isSubmitting,
    submitLabel = 'Save',
}: TagFormProps) {
    const form = useForm<TagFormValues>({
        resolver: zodResolver(tagSchema),
        defaultValues: {
            name: defaultValues?.name ?? '',
        },
    })

    return (
        <FormWrapper>
        <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                <FormField
                    control={form.control}
                    name="name"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Name</FormLabel>
                            <FormControl>
                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground text-lg">#</span>
                                    <Input
                                        placeholder="vacation"
                                        {...field}
                                    />
                                </div>
                            </FormControl>
                            <FormDescription>
                                Tag name without #. Only letters, numbers, _ and - allowed.
                            </FormDescription>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <Button type="submit" disabled={isSubmitting} className="w-full">
                    {isSubmitting ? 'Saving...' : submitLabel}
                </Button>
            </form>
        </Form>
        </FormWrapper>
    )
}
