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
    const formatted = value.toFixed(decimals)
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
