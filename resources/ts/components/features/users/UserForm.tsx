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
    FormDescription,
} from '@/components/ui/form'
import {
    createUserSchema,
    updateUserSchema,
    CreateUserFormData,
    UpdateUserFormData,
} from '@/schemas/users'

interface UserFormProps {
    defaultValues?: Partial<UpdateUserFormData>
    onSubmit: (data: CreateUserFormData | UpdateUserFormData) => void
    isSubmitting?: boolean
    submitLabel?: string
    isEdit?: boolean
}

export function UserForm({
    defaultValues,
    onSubmit,
    isSubmitting,
    submitLabel = 'Save',
    isEdit = false,
}: UserFormProps) {
    const form = useForm<CreateUserFormData | UpdateUserFormData>({
        resolver: zodResolver(isEdit ? updateUserSchema : createUserSchema),
        defaultValues: {
            name: '',
            email: '',
            password: '',
            ...defaultValues,
        },
    })

    return (
        <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="max-w-md space-y-4">
                <FormField
                    control={form.control}
                    name="name"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Name</FormLabel>
                            <FormControl>
                                <Input placeholder="John Doe" {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="email"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Email</FormLabel>
                            <FormControl>
                                <Input type="email" placeholder="john@example.com" {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="password"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>{isEdit ? 'New Password' : 'Password'}</FormLabel>
                            <FormControl>
                                <Input
                                    type="password"
                                    placeholder={isEdit ? 'Leave empty to keep current' : 'Enter password'}
                                    {...field}
                                />
                            </FormControl>
                            {isEdit && (
                                <FormDescription>
                                    Leave empty to keep the current password
                                </FormDescription>
                            )}
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <Button type="submit" disabled={isSubmitting} className="w-full">
                    {isSubmitting ? 'Saving...' : submitLabel}
                </Button>
            </form>
        </Form>
    )
}
