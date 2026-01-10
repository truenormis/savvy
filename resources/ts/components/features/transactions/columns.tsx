import { ColumnDef } from '@tanstack/react-table'
import { Transaction } from '@/types'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { MoreHorizontal, Pencil, Trash2, Copy, ArrowDownLeft, ArrowUpRight, ArrowLeftRight, ChevronRight, Banknote, HandCoins } from 'lucide-react'
import { Link } from 'react-router-dom'
import { cn } from '@/lib/utils'

const TYPE_CONFIG = {
    income: { icon: ArrowDownLeft, color: 'text-green-600', bg: 'bg-green-100', label: 'Income' },
    expense: { icon: ArrowUpRight, color: 'text-red-600', bg: 'bg-red-100', label: 'Expense' },
    transfer: { icon: ArrowLeftRight, color: 'text-blue-600', bg: 'bg-blue-100', label: 'Transfer' },
    debt_payment: { icon: Banknote, color: 'text-orange-600', bg: 'bg-orange-100', label: 'Debt Payment' },
    debt_collection: { icon: HandCoins, color: 'text-purple-600', bg: 'bg-purple-100', label: 'Debt Collection' },
}

export function createTransactionColumns(
    onDelete: (id: number) => void,
    onDuplicate: (id: number) => void,
    isReadOnly?: boolean
): ColumnDef<Transaction>[] {
    return [
        {
            id: 'expand',
            header: '',
            size: 32,
            cell: ({ row }) => {
                const itemsCount = row.original.itemsCount ?? row.original.items?.length ?? 0
                if (itemsCount <= 1) return null
                return (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => row.toggleExpanded()}
                        className="size-6"
                    >
                        <ChevronRight
                            className={cn(
                                'size-4 transition-transform',
                                row.getIsExpanded() && 'rotate-90'
                            )}
                        />
                    </Button>
                )
            },
        },
        {
            accessorKey: 'date',
            header: 'Date',
            cell: ({ row }) => (
                <span className="font-mono text-sm">
                    {new Date(row.original.date).toLocaleDateString()}
                </span>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }) => {
                const type = row.original.type
                const config = TYPE_CONFIG[type]
                const Icon = config.icon
                return (
                    <Badge variant="secondary" className={cn('gap-1', config.bg, config.color)}>
                        <Icon className="size-3" />
                        {config.label}
                    </Badge>
                )
            },
        },
        {
            accessorKey: 'description',
            header: 'Description',
            cell: ({ row }) => {
                const { description, category, account, toAccount, type, itemsCount, tags } = row.original

                const getDefaultDescription = () => {
                    if (type === 'transfer') return `${account.name} → ${toAccount?.name}`
                    if (type === 'debt_payment') return `Payment: ${toAccount?.name}`
                    if (type === 'debt_collection') return `Collection: ${toAccount?.name}`
                    return category?.name
                }

                const getSubDescription = () => {
                    if (type === 'transfer') {
                        return <span>{account.name} → {toAccount?.name}</span>
                    }
                    if (type === 'debt_payment') {
                        return <span>{account.name} → {toAccount?.name}</span>
                    }
                    if (type === 'debt_collection') {
                        return <span>{toAccount?.name} → {account.name}</span>
                    }
                    return (
                        <span>
                            {account.name}
                            {category && ` · ${category.icon} ${category.name}`}
                        </span>
                    )
                }

                return (
                    <div className="space-y-1">
                        <div className="font-medium">
                            {description || getDefaultDescription()}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {getSubDescription()}
                            {itemsCount != null && itemsCount > 0 && (
                                <span className="ml-2 text-primary">({itemsCount} items)</span>
                            )}
                        </div>
                        {tags && tags.length > 0 && (
                            <div className="flex flex-wrap gap-1">
                                {tags.map((tag) => (
                                    <Badge key={tag.id} variant="outline" className="text-xs px-1.5 py-0">
                                        #{tag.name}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </div>
                )
            },
        },
        {
            accessorKey: 'amount',
            header: () => <div className="text-right">Amount</div>,
            cell: ({ row }) => {
                const { type, amount, toAmount, account, toAccount } = row.original
                const isIncoming = type === 'income' || type === 'debt_collection'
                const isTransfer = type === 'transfer'
                const isDebtPayment = type === 'debt_payment'

                return (
                    <div className="text-right space-y-1">
                        <div className={cn(
                            'font-mono font-semibold',
                            isIncoming ? 'text-green-600' : isTransfer ? 'text-blue-600' : isDebtPayment ? 'text-orange-600' : 'text-red-600'
                        )}>
                            {isIncoming ? '+' : '-'}{amount.toFixed(account.currency?.decimals ?? 2)} {account.currency?.symbol}
                        </div>
                        {isTransfer && toAmount && toAccount && (
                            <div className="text-xs text-muted-foreground font-mono">
                                → +{toAmount.toFixed(toAccount.currency?.decimals ?? 2)} {toAccount.currency?.symbol}
                            </div>
                        )}
                    </div>
                )
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const transaction = row.original
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon-sm">
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <Link to={`/transactions/${transaction.id}/edit`}>
                                    <Pencil className="mr-2 size-4" />
                                    Edit
                                </Link>
                            </DropdownMenuItem>
                            {!isReadOnly && (
                            <>
                            <DropdownMenuItem onClick={() => onDuplicate(transaction.id)}>
                                <Copy className="mr-2 size-4" />
                                Duplicate
                            </DropdownMenuItem>
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <DropdownMenuItem
                                        onSelect={(e) => e.preventDefault()}
                                        className="text-destructive focus:text-destructive"
                                    >
                                        <Trash2 className="mr-2 size-4" />
                                        Delete
                                    </DropdownMenuItem>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Delete transaction?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This action cannot be undone. This will permanently delete
                                            this transaction and update account balances.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() => onDelete(transaction.id)}
                                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        >
                                            Delete
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                            </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )
            },
        },
    ]
}
