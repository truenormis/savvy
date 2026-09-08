import { z } from 'zod'

export const numeric = <T extends z.ZodType<number, number>>(schema: T) =>
    z
        .union([z.number(), z.string()])
        .transform((value) => (typeof value === 'number' ? value : value.trim() === '' ? NaN : Number(value)))
        .pipe(schema)

export type NumericInput = string | number
