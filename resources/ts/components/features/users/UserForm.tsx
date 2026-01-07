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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    createUserSchema,
    updateUserSchema,
    CreateUserFormData,
    UpdateUserFormData,
} from '@/schemas/users'
import { useUser } from '@/stores/auth'
import { FormWrapper } from '@/components/shared/FormWrapper'

interface UserFormProps {
    defaultValues?: Partial<UpdateUserFormData> & { id?: number }
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
    const currentUser = useUser()
    const isEditingSelf = isEdit && defaultValues?.id === currentUser?.id

    const form = useForm<CreateUserFormData | UpdateUserFormData>({
        resolver: zodResolver(isEdit ? updateUserSchema : createUserSchema),
        defaultValues: {
            name: '',
            email: '',
            password: '',
            role: 'read-only',
            ...defaultValues,
        },
    })

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

                    <FormField
                        control={form.control}
                        name="role"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Role</FormLabel>
                                <Select
                                    value={field.value}
                                    onValueChange={field.onChange}
                                    disabled={isEditingSelf}
                                >
                                    <FormControl>
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Select role" />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        <SelectItem value="admin">Admin</SelectItem>
                                        <SelectItem value="read-write">Read-Write</SelectItem>
                                        <SelectItem value="read-only">Read-Only</SelectItem>
                                    </SelectContent>
                                </Select>
                                {isEditingSelf && (
                                    <FormDescription>
                                        You cannot change your own role
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
        </FormWrapper>
    )
}
