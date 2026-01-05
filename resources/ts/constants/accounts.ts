import { Landmark, Wallet, Bitcoin, HandCoins } from 'lucide-react'
import type { AccountType, RegularAccountType } from '@/types'

export interface AccountTypeConfig {
    icon: typeof Landmark
    label: string
    color: string
    bgColor: string
    textColor: string
}

// All account types including debt (for display purposes)
export const ACCOUNT_TYPE_CONFIG: Record<AccountType, AccountTypeConfig> = {
    bank: {
        icon: Landmark,
        label: 'Bank',
        color: 'bg-blue-100 text-blue-700',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-600',
    },
    cash: {
        icon: Wallet,
        label: 'Cash',
        color: 'bg-green-100 text-green-700',
        bgColor: 'bg-green-100',
        textColor: 'text-green-600',
    },
    crypto: {
        icon: Bitcoin,
        label: 'Crypto',
        color: 'bg-orange-100 text-orange-700',
        bgColor: 'bg-orange-100',
        textColor: 'text-orange-600',
    },
    debt: {
        icon: HandCoins,
        label: 'Debt',
        color: 'bg-purple-100 text-purple-700',
        bgColor: 'bg-purple-100',
        textColor: 'text-purple-600',
    },
}

// Regular account types (excluding debt) - for account creation/selection
export const REGULAR_ACCOUNT_TYPES: RegularAccountType[] = ['bank', 'cash', 'crypto']

export const REGULAR_ACCOUNT_TYPE_CONFIG: Record<RegularAccountType, AccountTypeConfig> = {
    bank: ACCOUNT_TYPE_CONFIG.bank,
    cash: ACCOUNT_TYPE_CONFIG.cash,
    crypto: ACCOUNT_TYPE_CONFIG.crypto,
}
