import { z } from 'zod'
import { numeric } from './numeric'

export const accountSchema = z.object({
    name: z.string()
        .min(1, 'Name is required')
        .max(255, 'Maximum 255 characters'),

    type: z.enum(['bank', 'crypto', 'cash'], {
        error: 'Please select account type',
    }),

    currency_id: numeric(z.number().positive('Please select currency')),

    initial_balance: numeric(z.number().min(0, 'Balance cannot be negative')).default(0),

    is_active: z.boolean().default(true),
})

export type AccountFormData = z.output<typeof accountSchema>
export type AccountFormInput = z.input<typeof accountSchema>
