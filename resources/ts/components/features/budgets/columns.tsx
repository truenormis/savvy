import { Link } from 'react-router-dom'
import { ColumnDef } from '@tanstack/react-table'
import { Pencil, Trash2, MoreHorizontal } from 'lucide-react'
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
import { Budget } from '@/types'

const periodLabels: Record<string, string> = {
    weekly: 'Weekly',
    monthly: 'Monthly',
    yearly: 'Yearly',
    one_time: 'One-time',
}

export const createBudgetColumns = (
    onDelete: (id: number) => void,
    isReadOnly?: boolean
): ColumnDef<Budget>[] => [
    {
        accessorKey: 'name',
        header: 'Budget',
        cell: ({ row }) => (
            <div>
                <p className="font-medium">{row.original.name}</p>
                <p className="text-xs text-muted-foreground">
                    {row.original.isGlobal
                        ? 'All expenses'
                        : row.original.categories.map(c => c.name).join(', ') || 'No categories'}
                </p>
            </div>
        ),
    },
    {
        accessorKey: 'amount',
        header: 'Limit',
        cell: ({ row }) => (
            <span className="font-mono font-medium">
                {row.original.amount.toLocaleString()} {row.original.currency?.symbol ?? ''}
            </span>
        ),
    },
    {
        accessorKey: 'period',
        header: 'Period',
        cell: ({ row }) => (
            <Badge variant="outline">
                {periodLabels[row.original.period] || row.original.period}
            </Badge>
        ),
    },
    {
        accessorKey: 'progress',
        header: 'Progress',
        cell: ({ row }) => {
            const progress = row.original.progress
            if (!progress) return null

            const isExceeded = progress.is_exceeded
            const percent = Math.min(progress.percent, 100)
            const symbol = row.original.currency?.symbol ?? ''

            return (
                <div className="w-44 space-y-1">
                    <div className="flex justify-between text-xs">
                        <span>{progress.spent.toLocaleString()} {symbol} spent</span>
                        <span className={isExceeded ? 'text-red-600 font-medium' : ''}>
                            {progress.percent.toFixed(0)}%
                        </span>
                    </div>
                    <Progress
                        value={percent}
                        className={`h-2 ${isExceeded ? '[&>div]:bg-red-500' : ''}`}
                    />
                    <p className="text-xs text-muted-foreground">
                        {isExceeded
                            ? `Exceeded by ${(progress.spent - row.original.amount).toLocaleString()} ${symbol}`
                            : `${progress.remaining.toLocaleString()} ${symbol} remaining`}
                    </p>
                </div>
            )
        },
    },
    {
        accessorKey: 'isActive',
        header: 'Status',
        cell: ({ row }) => (
            <Badge variant={row.original.isActive ? 'default' : 'secondary'}>
                {row.original.isActive ? 'Active' : 'Inactive'}
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
                        <Link to={`/budgets/${row.original.id}/edit`}>
                            <Pencil className="mr-2 h-4 w-4" />
                            Edit
                        </Link>
                    </DropdownMenuItem>
                    {!isReadOnly && (
                    <>
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
                                <AlertDialogTitle>Delete budget?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    This action cannot be undone. The budget "{row.original.name}"
                                    will be permanently deleted.
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
                    </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        ),
    },
]
