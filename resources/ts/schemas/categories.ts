import { z } from 'zod'

export const categorySchema = z.object({
    name: z.string()
        .min(2, 'Minimum 2 characters')
        .max(50, 'Maximum 50 characters'),

    type: z.enum(['income', 'expense'], {
        message: 'Please select a category type',
    }),

    icon: z.string()
        .min(1, 'Please select an icon'),

    color: z.string()
        .regex(/^#[0-9A-Fa-f]{6}$/, 'Invalid color format (expected: #RRGGBB)'),
})

export type CategoryFormData = z.infer<typeof categorySchema>
