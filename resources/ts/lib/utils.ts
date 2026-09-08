import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs))
}

/**
 * Format a number as a currency amount with the correct number of decimal places
 * @param value - The numeric value to format
 * @param decimals - Number of decimal places (from currency settings)
 * @param symbol - Currency symbol (optional, defaults to empty string)
 * @param showSymbol - Whether to show the currency symbol (defaults to true)
 */
export function formatAmount(
    value: number,
    decimals: number = 2,
    symbol: string = '',
    showSymbol: boolean = true
): string {
    const formatted = formatNumber(value, decimals)
    return showSymbol && symbol ? `${formatted} ${symbol}` : formatted
}

/**
 * Format a number with locale-aware separators and correct decimal places
 * @param value - The numeric value to format
 * @param decimals - Number of decimal places (from currency settings)
 */
export function formatNumber(value: number, decimals: number = 2): string {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value)
}

export function toId(value: unknown): number | null {
    if (value === null || value === undefined || value === '') return null

    const parsed = Number(value)

    return Number.isNaN(parsed) ? null : parsed
}

export const APP_LOCALE = 'en-US'

const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/

export function parseDate(value: string | Date): Date | null {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value
    }

    const dateOnly = DATE_ONLY.exec(value)

    const date = dateOnly
        ? new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]))
        : new Date(value)

    return Number.isNaN(date.getTime()) ? null : date
}

export function formatDate(
    value: string | Date,
    options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric' }
): string {
    const date = parseDate(value)

    return date ? date.toLocaleDateString(APP_LOCALE, options) : ''
}

export function formatShortDate(value: string | Date): string {
    return formatDate(value, { month: 'short', day: 'numeric' })
}

export function toDateString(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${date.getFullYear()}-${month}-${day}`
}

export function today(): string {
    return toDateString(new Date())
}

export function isSameDay(a: Date, b: Date): boolean {
    return toDateString(a) === toDateString(b)
}
