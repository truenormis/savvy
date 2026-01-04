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
import { Category } from '@/types'

export const createCategoryColumns = (
    onDelete: (id: number) => void
): ColumnDef<Category>[] => [
    {
        accessorKey: 'id',
        header: 'ID',
        cell: ({ row }) => (
            <span className="text-muted-foreground font-mono text-sm">
                #{row.original.id}
            </span>
        ),
    },
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => (
            <div className="flex items-center gap-3">
                <div
                    className="w-10 h-10 rounded-lg flex items-center justify-center text-white text-lg shadow-sm"
                    style={{ backgroundColor: row.original.color }}
                >
                    {row.original.icon}
                </div>
                <div>
                    <p className="font-medium">{row.original.name}</p>
                    <p className="text-xs text-muted-foreground">
                        Created: {new Date(row.original.createdAt!).toLocaleDateString('en-US')}
                    </p>
                </div>
            </div>
        ),
    },
    {
        accessorKey: 'type',
        header: 'Type',
        cell: ({ row }) => (
            <Badge
                variant={row.original.type === 'income' ? 'default' : 'destructive'}
                className={
                    row.original.type === 'income'
                        ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-100'
                        : 'bg-red-100 text-red-700 hover:bg-red-100'
                }
            >
                {row.original.type === 'income' ? '↑ Income' : '↓ Expense'}
            </Badge>
        ),
    },
    {
        accessorKey: 'color',
        header: 'Color',
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <div
                    className="w-6 h-6 rounded-md border shadow-sm"
                    style={{ backgroundColor: row.original.color }}
                />
                <code className="text-xs bg-muted px-2 py-1 rounded">
                    {row.original.color}
                </code>
            </div>
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
                        <Link to={`/categories/${row.original.id}/edit`}>
                            <Pencil className="mr-2 h-4 w-4" />
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
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete
                            </DropdownMenuItem>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Delete category?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    This action cannot be undone. The category "{row.original.name}"
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
                </DropdownMenuContent>
            </DropdownMenu>
        ),
    },
]
