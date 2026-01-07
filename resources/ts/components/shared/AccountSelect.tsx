import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { FormControl } from '@/components/ui/form'
import { useAccounts } from '@/hooks'
import { ACCOUNT_TYPE_CONFIG } from '@/constants'
import { cn } from '@/lib/utils'
import { Wallet } from 'lucide-react'
import type { AccountType } from '@/types'

interface AccountSelectProps {
    value?: number | null
    onChange: (value: number) => void
    excludeId?: number | null
    excludeDebts?: boolean
    activeOnly?: boolean
    placeholder?: string
    disabled?: boolean
}

export function AccountSelect({
    value,
    onChange,
    excludeId,
    excludeDebts = true,
    activeOnly = true,
    placeholder = 'Select account',
    disabled,
}: AccountSelectProps) {
    const { data: accounts } = useAccounts({ active: activeOnly, exclude_debts: excludeDebts })

    const filteredAccounts = accounts?.filter(a => {
        if (excludeId && a.id === excludeId) return false
        return true
    })

    return (
        <Select
            onValueChange={(val) => onChange(Number(val))}
            value={value ? value.toString() : undefined}
            disabled={disabled}
        >
            <FormControl>
                <SelectTrigger>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
            </FormControl>
            <SelectContent>
                {filteredAccounts?.map((account) => {
                    const config = ACCOUNT_TYPE_CONFIG[account.type as AccountType]
                    const Icon = config?.icon || Wallet
                    return (
                        <SelectItem
                            key={account.id}
                            value={account.id.toString()}
                        >
                            <div className="flex items-center gap-2">
                                <Icon className={cn('size-4', config?.textColor)} />
                                <span>{account.name}</span>
                                <span className="text-muted-foreground">
                                    {account.currency?.symbol}
                                </span>
                            </div>
                        </SelectItem>
                    )
                })}
            </SelectContent>
        </Select>
    )
}
