import { z } from 'zod'

export const currencySchema = z.object({
    code: z.string()
        .min(1, 'Currency code is required')
        .max(10, 'Maximum 10 characters')
        .toUpperCase(),

    name: z.string()
        .min(2, 'Minimum 2 characters')
        .max(255, 'Maximum 255 characters'),

    symbol: z.string()
        .min(1, 'Symbol is required')
        .max(5, 'Maximum 5 characters'),

    decimals: z.coerce.number()
        .int()
        .min(0, 'Minimum 0')
        .max(8, 'Maximum 8'),

    rate: z.coerce.number()
        .positive('Rate must be positive')
        .optional(),
})

export type CurrencyFormData = z.infer<typeof currencySchema>
