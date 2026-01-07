import { Link } from 'react-router-dom'
import { ColumnDef } from '@tanstack/react-table'
import { Pencil, Trash2, MoreHorizontal, SkipForward, ArrowDownLeft, ArrowUpRight, ArrowLeftRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
import { RecurringTransaction } from '@/types'
import { cn } from '@/lib/utils'

const typeConfig = {
    income: { icon: ArrowDownLeft, color: 'text-green-500', label: 'Income' },
    expense: { icon: ArrowUpRight, color: 'text-red-500', label: 'Expense' },
    transfer: { icon: ArrowLeftRight, color: 'text-blue-500', label: 'Transfer' },
}

const frequencyLabels: Record<string, string> = {
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
    yearly: 'Yearly',
}

interface ColumnsOptions {
    onDelete: (id: number) => void
    onSkip: (id: number) => void
}

export const createRecurringColumns = ({
    onDelete,
    onSkip,
}: ColumnsOptions): ColumnDef<RecurringTransaction>[] => [
    {
        accessorKey: 'description',
        header: 'Transaction',
        cell: ({ row }) => {
            const config = typeConfig[row.original.type]
            const Icon = config.icon
            return (
                <div className="flex items-center gap-3">
                    <div className={cn('p-2 rounded-full bg-muted', config.color)}>
                        <Icon className="h-4 w-4" />
                    </div>
                    <div>
                        <p className="font-medium">
                            {row.original.description || config.label}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {row.original.account.name}
                            {row.original.type === 'transfer' && row.original.toAccount && (
                                <> → {row.original.toAccount.name}</>
                            )}
                        </p>
                    </div>
                </div>
            )
        },
    },
    {
        accessorKey: 'amount',
        header: 'Amount',
        cell: ({ row }) => (
            <span className={cn(
                'font-mono font-medium',
                row.original.type === 'income' && 'text-green-600',
                row.original.type === 'expense' && 'text-red-600'
            )}>
                {row.original.type === 'expense' && '-'}
                {row.original.amount.toLocaleString()} {row.original.account.currency?.symbol}
            </span>
        ),
    },
    {
        accessorKey: 'category',
        header: 'Category',
        cell: ({ row }) => {
            if (!row.original.category) return <span className="text-muted-foreground">-</span>
            return (
                <div className="flex items-center gap-2">
                    <span
                        className="w-5 h-5 rounded flex items-center justify-center text-xs text-white"
                        style={{ backgroundColor: row.original.category.color }}
                    >
                        {row.original.category.icon}
                    </span>
                    <span>{row.original.category.name}</span>
                </div>
            )
        },
    },
    {
        accessorKey: 'frequency',
        header: 'Frequency',
        cell: ({ row }) => {
            const interval = row.original.interval
            const freq = frequencyLabels[row.original.frequency]
            const label = interval === 1 ? freq : `Every ${interval} ${freq.toLowerCase()}`
            return <Badge variant="outline">{label}</Badge>
        },
    },
    {
        accessorKey: 'nextRunDate',
        header: 'Next Run',
        cell: ({ row }) => {
            const date = new Date(row.original.nextRunDate)
            const isToday = date.toDateString() === new Date().toDateString()
            const isTomorrow = date.toDateString() === new Date(Date.now() + 86400000).toDateString()

            return (
                <div>
                    <p className={cn('font-medium', isToday && 'text-orange-500')}>
                        {isToday ? 'Today' : isTomorrow ? 'Tomorrow' : date.toLocaleDateString()}
                    </p>
                    {row.original.lastRunDate && (
                        <p className="text-xs text-muted-foreground">
                            Last: {new Date(row.original.lastRunDate).toLocaleDateString()}
                        </p>
                    )}
                </div>
            )
        },
    },
    {
        accessorKey: 'isActive',
        header: 'Status',
        cell: ({ row }) => (
            <Badge variant={row.original.isActive ? 'default' : 'secondary'}>
                {row.original.isActive ? 'Active' : 'Paused'}
            </Badge>
        ),
    },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="h-8 w-8">
                        <MoreHorizontal className="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem asChild>
                        <Link to={`/recurring/${row.original.id}/edit`}>
                            <Pencil className="mr-2 h-4 w-4" />
                            Edit
                        </Link>
                    </DropdownMenuItem>
                    {row.original.isActive && (
                        <DropdownMenuItem onClick={() => onSkip(row.original.id)}>
                            <SkipForward className="mr-2 h-4 w-4" />
                            Skip Next
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuSeparator />
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <DropdownMenuItem
                                onSelect={(e) => e.preventDefault()}
                                className="text-destructive focus:text-destructive"
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete
                            </DropdownMenuItem>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Delete recurring transaction?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    This action cannot be undone. This recurring transaction will be
                                    permanently deleted. Existing transactions created from it will not be affected.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() => onDelete(row.original.id)}
                                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                >
                                    Delete
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </DropdownMenuContent>
            </DropdownMenu>
        ),
    },
]
