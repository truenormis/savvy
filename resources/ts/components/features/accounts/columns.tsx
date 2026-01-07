import { Link } from 'react-router-dom'
import { ColumnDef } from '@tanstack/react-table'
import { Pencil, Trash2, MoreHorizontal } from 'lucide-react'
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
import { Account } from '@/types'
import { ACCOUNT_TYPE_CONFIG } from '@/constants'

function formatBalance(amount: number, currency?: { symbol: string; decimals: number }) {
    if (!currency) return amount.toFixed(2)
    return `${currency.symbol}${amount.toFixed(currency.decimals)}`
}

export const createAccountColumns = (
    onDelete: (id: number) => void,
    isReadOnly?: boolean
): ColumnDef<Account>[] => [
    {
        accessorKey: 'name',
        header: 'Account',
        cell: ({ row }) => {
            const config = ACCOUNT_TYPE_CONFIG[row.original.type]
            const Icon = config.icon

            return (
                <div className="flex items-center gap-3">
                    <div className={`p-2 rounded-lg ${config.color}`}>
                        <Icon className="size-5" />
                    </div>
                    <div>
                        <p className="font-medium">{row.original.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {row.original.currency?.code ?? 'N/A'}
                        </p>
                    </div>
                </div>
            )
        },
    },
    {
        accessorKey: 'type',
        header: 'Type',
        cell: ({ row }) => {
            const config = ACCOUNT_TYPE_CONFIG[row.original.type]
            return (
                <Badge variant="secondary" className={config.color}>
                    {config.label}
                </Badge>
            )
        },
    },
    {
        accessorKey: 'currentBalance',
        header: 'Balance',
        cell: ({ row }) => (
            <div className="font-mono text-right">
                <p className={row.original.currentBalance >= 0 ? 'text-green-600' : 'text-red-600'}>
                    {formatBalance(row.original.currentBalance, row.original.currency)}
                </p>
                {row.original.initialBalance !== row.original.currentBalance && (
                    <p className="text-xs text-muted-foreground">
                        Initial: {formatBalance(row.original.initialBalance, row.original.currency)}
                    </p>
                )}
            </div>
        ),
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
                    <Button variant="ghost" size="icon" className="size-8">
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem asChild>
                        <Link to={`/accounts/${row.original.id}/edit`}>
                            <Pencil className="mr-2 size-4" />
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
                                <Trash2 className="mr-2 size-4" />
                                Delete
                            </DropdownMenuItem>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Delete account?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    This action cannot be undone. The account "{row.original.name}"
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
