import { z } from 'zod'
import { numeric } from './numeric'

export const debtSchema = z.object({
    name: z.string()
        .min(1, 'Name is required')
        .max(255, 'Maximum 255 characters'),

    debt_type: z.enum(['i_owe', 'owed_to_me'], {
        error: 'Please select debt type',
    }),

    currency_id: numeric(z.number().positive('Please select currency')),

    amount: numeric(z.number().positive('Amount must be greater than 0')),

    due_date: z.string().optional(),

    counterparty: z.string().max(255).optional(),

    description: z.string().max(1000).optional(),
})

export type DebtFormData = z.output<typeof debtSchema>
export type DebtFormInput = z.input<typeof debtSchema>

export const debtPaymentSchema = z.object({
    account_id: numeric(z.number().positive('Please select account')),

    amount: numeric(z.number().positive('Amount must be greater than 0')),

    date: z.string().min(1, 'Date is required'),

    description: z.string().max(1000).optional(),
})

export type DebtPaymentFormData = z.output<typeof debtPaymentSchema>
export type DebtPaymentFormInput = z.input<typeof debtPaymentSchema>
