import { z } from 'zod'
import { numeric } from './numeric'

export const budgetSchema = z.object({
    name: z.string()
        .min(2, 'Minimum 2 characters')
        .max(100, 'Maximum 100 characters'),

    amount: numeric(z.number().min(0.01, 'Amount must be greater than 0')),

    currency_id: numeric(z.number()).nullable().optional(),

    period: z.enum(['weekly', 'monthly', 'yearly', 'one_time'], {
        message: 'Please select a period',
    }),

    start_date: z.string().nullable().optional(),
    end_date: z.string().nullable().optional(),

    is_global: z.boolean().default(false),

    notify_at_percent: numeric(z.number().min(1).max(100)).nullable().optional(),

    is_active: z.boolean().default(true),

    category_ids: z.array(z.number()).default([]),

    tag_ids: z.array(z.number()).default([]),
})

export type BudgetFormData = z.output<typeof budgetSchema>
export type BudgetFormInput = z.input<typeof budgetSchema>
