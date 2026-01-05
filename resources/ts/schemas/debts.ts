import { z } from 'zod'

export const debtSchema = z.object({
    name: z.string()
        .min(1, 'Name is required')
        .max(255, 'Maximum 255 characters'),

    debt_type: z.enum(['i_owe', 'owed_to_me'], {
        required_error: 'Please select debt type',
    }),

    currency_id: z.coerce.number({
        required_error: 'Please select currency',
    }).positive('Please select currency'),

    amount: z.coerce.number()
        .positive('Amount must be greater than 0'),

    due_date: z.string().optional(),

    counterparty: z.string().max(255).optional(),

    description: z.string().max(1000).optional(),
})

export type DebtFormData = z.infer<typeof debtSchema>

export const debtPaymentSchema = z.object({
    account_id: z.coerce.number({
        required_error: 'Please select account',
    }).positive('Please select account'),

    amount: z.coerce.number()
        .positive('Amount must be greater than 0'),

    date: z.string().min(1, 'Date is required'),

    description: z.string().max(1000).optional(),
})

export type DebtPaymentFormData = z.infer<typeof debtPaymentSchema>
