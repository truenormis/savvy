import { z } from 'zod'

export const transactionItemSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    quantity: z.coerce.number().int('Must be an integer').min(1, 'Must be at least 1'),
    price_per_unit: z.coerce.number().min(0, 'Cannot be negative'),
})

export const transactionSchema = z.object({
    type: z.enum(['income', 'expense', 'transfer'], {
        required_error: 'Please select transaction type',
    }),

    account_id: z.coerce.number({
        required_error: 'Please select account',
    }).positive('Please select account'),

    to_account_id: z.coerce.number().positive().optional().nullable(),

    category_id: z.coerce.number().positive().optional().nullable(),

    amount: z.coerce.number({
        required_error: 'Amount is required',
    }).positive('Amount must be positive'),

    to_amount: z.coerce.number().positive().optional().nullable(),

    exchange_rate: z.coerce.number().positive().optional().nullable(),

    description: z.string().max(500).optional(),

    date: z.preprocess(
        (val) => val ?? new Date().toISOString().split('T')[0],
        z.string().min(1, 'Date is required')
    ),

    items: z.array(transactionItemSchema).optional(),

    tag_ids: z.array(z.number()).optional(),
}).superRefine((data, ctx) => {
    // Transfer requires to_account_id
    if (data.type === 'transfer' && !data.to_account_id) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Destination account is required for transfers',
            path: ['to_account_id'],
        })
    }

    // Transfer should not have category
    if (data.type === 'transfer' && data.category_id) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Category should not be set for transfers',
            path: ['category_id'],
        })
    }

    // Income/Expense should have category
    if (data.type !== 'transfer' && !data.category_id) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Please select a category',
            path: ['category_id'],
        })
    }

    // Validate items total matches amount (only if there are items with values)
    const items = data.items ?? []
    if (items.length > 0) {
        const itemsTotal = items.reduce((sum, item) => sum + item.quantity * item.price_per_unit, 0)
        if (itemsTotal > 0 && Math.abs(itemsTotal - data.amount) > 0.01) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: `Items total (${itemsTotal.toFixed(2)}) must equal amount (${data.amount.toFixed(2)})`,
                path: ['items'],
            })
        }
    }
})

export type TransactionFormValues = z.infer<typeof transactionSchema>
export type TransactionItemFormValues = z.infer<typeof transactionItemSchema>
