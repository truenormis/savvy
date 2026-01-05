import { Link } from 'react-router-dom'
import { ColumnDef } from '@tanstack/react-table'
import { Pencil, Trash2, MoreHorizontal, HandCoins, Banknote, RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
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
import { Debt } from '@/types'

const DEBT_TYPE_CONFIG = {
    i_owe: {
        icon: Banknote,
        color: 'bg-red-100',
        textColor: 'text-red-600',
        label: 'I Owe'
    },
    owed_to_me: {
        icon: HandCoins,
        color: 'bg-green-100',
        textColor: 'text-green-600',
        label: 'Owed to Me'
    },
}

function formatAmount(amount: number, currency?: { symbol: string; decimals: number }) {
    if (!currency) return amount.toFixed(2)
    return `${currency.symbol}${amount.toFixed(currency.decimals)}`
}

interface ColumnActions {
    onDelete: (id: number) => void
    onPayment: (debt: Debt) => void
    onCollect: (debt: Debt) => void
    onReopen: (id: number) => void
}

export const createDebtColumns = (
    actions: ColumnActions
): ColumnDef<Debt>[] => [
    {
        accessorKey: 'name',
        header: 'Debt',
        cell: ({ row }) => {
            const config = DEBT_TYPE_CONFIG[row.original.debtType]
            const Icon = config.icon

            return (
                <div className="flex items-center gap-3">
                    <div className={`p-2 rounded-lg ${config.color}`}>
                        <Icon className={`size-5 ${config.textColor}`} />
                    </div>
                    <div>
                        <p className="font-medium">{row.original.name}</p>
                        {row.original.counterparty && (
                            <p className="text-xs text-muted-foreground">
                                {row.original.counterparty}
                            </p>
                        )}
                    </div>
                </div>
            )
        },
    },
    {
        accessorKey: 'debtType',
        header: 'Type',
        cell: ({ row }) => {
            const config = DEBT_TYPE_CONFIG[row.original.debtType]
            return (
                <Badge variant="secondary" className={`${config.color} ${config.textColor}`}>
                    {config.label}
                </Badge>
            )
        },
    },
    {
        accessorKey: 'progress',
        header: 'Progress',
        cell: ({ row }) => {
            const debt = row.original
            const progress = debt.paymentProgress

            return (
                <div className="w-32">
                    <Progress value={progress} className="h-2" />
                    <div className="flex justify-between text-xs text-muted-foreground mt-1">
                        <span>{formatAmount(debt.currentBalance, debt.currency)}</span>
                        <span>{progress.toFixed(0)}%</span>
                    </div>
                </div>
            )
        },
    },
    {
        accessorKey: 'targetAmount',
        header: 'Total',
        cell: ({ row }) => (
            <div className="font-mono text-right">
                {formatAmount(row.original.targetAmount, row.original.currency)}
            </div>
        ),
    },
    {
        accessorKey: 'remainingDebt',
        header: 'Remaining',
        cell: ({ row }) => (
            <div className={`font-mono text-right ${row.original.isPaidOff ? 'text-green-600' : 'text-orange-600'}`}>
                {row.original.isPaidOff
                    ? 'Paid Off'
                    : formatAmount(row.original.remainingDebt, row.original.currency)
                }
            </div>
        ),
    },
    {
        accessorKey: 'dueDate',
        header: 'Due Date',
        cell: ({ row }) => {
            if (!row.original.dueDate) return <span className="text-muted-foreground">-</span>

            const dueDate = new Date(row.original.dueDate)
            const isOverdue = !row.original.isPaidOff && dueDate < new Date()

            return (
                <span className={isOverdue ? 'text-red-600 font-medium' : ''}>
                    {dueDate.toLocaleDateString()}
                </span>
            )
        },
    },
    {
        accessorKey: 'isActive',
        header: 'Status',
        cell: ({ row }) => (
            <Badge variant={row.original.isPaidOff ? 'default' : row.original.isActive ? 'secondary' : 'outline'}>
                {row.original.isPaidOff ? 'Completed' : row.original.isActive ? 'Active' : 'Inactive'}
            </Badge>
        ),
    },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) => {
            const debt = row.original

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-8">
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {!debt.isPaidOff && (
                            <>
                                {debt.debtType === 'i_owe' ? (
                                    <DropdownMenuItem onClick={() => actions.onPayment(debt)}>
                                        <Banknote className="mr-2 size-4" />
                                        Make Payment
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem onClick={() => actions.onCollect(debt)}>
                                        <HandCoins className="mr-2 size-4" />
                                        Collect Payment
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuSeparator />
                            </>
                        )}
                        {debt.isPaidOff && (
                            <>
                                <DropdownMenuItem onClick={() => actions.onReopen(debt.id)}>
                                    <RotateCcw className="mr-2 size-4" />
                                    Reopen
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                            </>
                        )}
                        <DropdownMenuItem asChild>
                            <Link to={`/debts/${debt.id}/edit`}>
                                <Pencil className="mr-2 size-4" />
                                Edit
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
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
                                    <AlertDialogTitle>Delete debt?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        This action cannot be undone. The debt "{debt.name}"
                                        will be permanently deleted.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={() => actions.onDelete(debt.id)}
                                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                    >
                                        Delete
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </DropdownMenuContent>
                </DropdownMenu>
            )
        },
    },
]
