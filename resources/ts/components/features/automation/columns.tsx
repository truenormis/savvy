import { ColumnDef } from '@tanstack/react-table'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { MoreHorizontal, Pencil, Trash2, History } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { AutomationRule } from '@/types/automation'

interface ColumnOptions {
    onDelete: (id: number) => void
    onToggle: (id: number) => void
    isReadOnly?: boolean
}

export function createAutomationColumns({ onDelete, onToggle, isReadOnly }: ColumnOptions): ColumnDef<AutomationRule>[] {
    return [
        {
            accessorKey: 'priority',
            header: '#',
            cell: ({ row }) => (
                <span className="text-muted-foreground text-sm">{row.original.priority}</span>
            ),
            size: 50,
        },
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{row.original.name}</div>
                    {row.original.description && (
                        <div className="text-sm text-muted-foreground truncate max-w-xs">
                            {row.original.description}
                        </div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'trigger_type',
            header: 'Trigger',
            cell: ({ row }) => (
                <Badge variant="outline">{row.original.trigger_label}</Badge>
            ),
        },
        {
            accessorKey: 'conditions',
            header: 'Conditions',
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.conditions.conditions.length} condition(s)
                </span>
            ),
        },
        {
            accessorKey: 'actions',
            header: 'Actions',
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.actions.length} action(s)
                </span>
            ),
        },
        {
            accessorKey: 'runs_count',
            header: 'Runs',
            cell: ({ row }) => (
                <span className="text-sm">{row.original.runs_count}</span>
            ),
        },
        {
            accessorKey: 'is_active',
            header: 'Active',
            cell: ({ row }) => (
                <Switch
                    checked={row.original.is_active}
                    disabled={isReadOnly}
                    onCheckedChange={() => onToggle(row.original.id)}
                />
            ),
        },
        {
            id: 'actions',
            cell: ({ row }) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon">
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link to={`/automation/${row.original.id}/edit`}>
                                <Pencil className="size-4 mr-2" />
                                Edit
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link to={`/automation/${row.original.id}/logs`}>
                                <History className="size-4 mr-2" />
                                View Logs
                            </Link>
                        </DropdownMenuItem>
                        {!isReadOnly && (
                        <DropdownMenuItem
                            className="text-destructive"
                            onClick={() => onDelete(row.original.id)}
                        >
                            <Trash2 className="size-4 mr-2" />
                            Delete
                        </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ]
}
