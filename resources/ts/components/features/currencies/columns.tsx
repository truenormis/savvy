import { Link } from 'react-router-dom'
import { ColumnDef } from '@tanstack/react-table'
import { Pencil, Trash2, MoreHorizontal, Star } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
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
import { Currency } from '@/types'

interface ColumnOptions {
    onDelete: (id: number) => void
    onSetBase: (id: number) => void
    isSettingBase?: boolean
}

export const createCurrencyColumns = ({
    onDelete,
    onSetBase,
    isSettingBase,
}: ColumnOptions): ColumnDef<Currency>[] => [
    {
        accessorKey: 'code',
        header: 'Code',
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <span className="font-mono font-semibold">{row.original.code}</span>
                {row.original.isBase && (
                    <Badge variant="secondary" className="gap-1">
                        <Star className="size-3 fill-current" />
                        Base
                    </Badge>
                )}
            </div>
        ),
    },
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => (
            <div>
                <p className="font-medium">{row.original.name}</p>
                <p className="text-xs text-muted-foreground">
                    Symbol: {row.original.symbol}
                </p>
            </div>
        ),
    },
    {
        accessorKey: 'rate',
        header: 'Rate',
        cell: ({ row }) => (
            <div className="font-mono">
                {row.original.isBase ? (
                    <span className="text-muted-foreground">1.000000</span>
                ) : (
                    <span>{row.original.rate.toFixed(6)}</span>
                )}
            </div>
        ),
    },
    {
        accessorKey: 'decimals',
        header: 'Decimals',
        cell: ({ row }) => (
            <span className="text-muted-foreground">{row.original.decimals}</span>
        ),
    },
    {
        id: 'isBase',
        header: 'Base',
        cell: ({ row }) => (
            <Switch
                checked={row.original.isBase}
                disabled={row.original.isBase || isSettingBase}
                onCheckedChange={() => onSetBase(row.original.id)}
            />
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
                        <Link to={`/currencies/${row.original.id}/edit`}>
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
                                disabled={row.original.isBase}
                            >
                                <Trash2 className="mr-2 size-4" />
                                Delete
                            </DropdownMenuItem>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Delete currency?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    This action cannot be undone. The currency "{row.original.name}" ({row.original.code})
                                    will be permanently deleted.
                                    {row.original.isBase && (
                                        <span className="block mt-2 text-destructive font-medium">
                                            Cannot delete base currency.
                                        </span>
                                    )}
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() => onDelete(row.original.id)}
                                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                    disabled={row.original.isBase}
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
