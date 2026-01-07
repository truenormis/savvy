import { z } from 'zod'

export const recurringSchema = z.object({
    type: z.enum(['income', 'expense', 'transfer'], {
        message: 'Please select a type',
    }),

    account_id: z.coerce.number({
        message: 'Please select an account',
    }).min(1, 'Please select an account'),

    to_account_id: z.coerce.number().nullable().optional(),

    category_id: z.coerce.number().nullable().optional(),

    amount: z.coerce.number()
        .min(0.01, 'Amount must be greater than 0'),

    to_amount: z.coerce.number().nullable().optional(),

    description: z.string().max(255).nullable().optional(),

    frequency: z.enum(['daily', 'weekly', 'monthly', 'yearly'], {
        message: 'Please select a frequency',
    }),

    interval: z.coerce.number()
        .min(1, 'Interval must be at least 1')
        .max(365, 'Interval must be less than 365')
        .default(1),

    day_of_week: z.coerce.number().min(0).max(6).nullable().optional(),

    day_of_month: z.coerce.number().min(1).max(31).nullable().optional(),

    start_date: z.string({
        message: 'Please select a start date',
    }),

    end_date: z.string().nullable().optional(),

    is_active: z.boolean().default(true),

    tag_ids: z.array(z.number()).default([]),
}).superRefine((data, ctx) => {
    // Require category for income/expense
    if ((data.type === 'income' || data.type === 'expense') && !data.category_id) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Please select a category',
            path: ['category_id'],
        })
    }

    // Require to_account_id for transfer
    if (data.type === 'transfer' && !data.to_account_id) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Please select destination account',
            path: ['to_account_id'],
        })
    }

    // Don't allow same account for transfer
    if (data.type === 'transfer' && data.account_id === data.to_account_id) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Destination must be different from source',
            path: ['to_account_id'],
        })
    }
})

export type RecurringFormData = z.infer<typeof recurringSchema>
