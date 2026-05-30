import { Link } from 'react-router-dom'
import { Trash2, MoreHorizontal, PlugZap, ArrowUpRight } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
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
import { BrandTile, brandVars } from './BrandIcon'
import type { IdentityProvider } from '@/types/sso'

function StatusPill({ enabled }: { enabled: boolean }) {
    if (enabled) {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-60" />
                    <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                </span>
                Active
            </span>
        )
    }

    return (
        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
            <span className="size-2 rounded-full border border-muted-foreground/50" />
            Disabled
        </span>
    )
}

interface ProviderCardProps {
    provider: IdentityProvider
    index: number
    onDelete: (id: number) => void
    onTest: (id: number) => void
}

export function ProviderCard({ provider, index, onDelete, onTest }: ProviderCardProps) {
    return (
        <div
            style={{ ...brandVars(provider.preset), animationDelay: `${index * 45}ms` }}
            className={cn(
                'group relative flex flex-col overflow-hidden rounded-xl border bg-card p-5 shadow-sm',
                'fill-mode-both animate-in fade-in slide-in-from-bottom-3 duration-500',
                'transition-[box-shadow,border-color] duration-300 hover:border-border/80 hover:shadow-md',
                !provider.enabled && 'opacity-65 grayscale-[0.45] hover:opacity-100 hover:grayscale-0',
            )}
        >
            <Link
                to={`/settings/providers/${provider.id}/edit`}
                className="absolute inset-0 z-0 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <span className="sr-only">Edit {provider.name}</span>
            </Link>

            <div className="relative z-10 flex items-start justify-between gap-3">
                <BrandTile
                    preset={provider.preset}
                    className="size-12 shrink-0 transition-transform duration-300 group-hover:scale-105"
                    iconClassName="size-6"
                />

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground opacity-0 transition-opacity focus-visible:opacity-100 group-hover:opacity-100"
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => onTest(provider.id)}>
                            <PlugZap className="mr-2 size-4" />
                            Test connection
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
                                    <AlertDialogTitle>Delete provider?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        This action cannot be undone. "{provider.name}" will be permanently removed.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={() => onDelete(provider.id)}
                                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                    >
                                        Delete
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div className="mt-4 min-w-0">
                <p className="truncate text-base font-semibold leading-tight">{provider.name}</p>
                <p className="mt-1 truncate font-mono text-xs text-muted-foreground">/{provider.slug}</p>
            </div>

            <div className="mt-5 flex items-center justify-between">
                <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                    {provider.protocol}
                </span>
                <StatusPill enabled={provider.enabled} />
            </div>
        </div>
    )
}

const EMPTY_BRANDS = ['github', 'google', 'entra', 'okta', 'gitlab']

export function ProvidersEmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
            <div className="mb-8 flex items-end">
                {EMPTY_BRANDS.map((preset, i) => {
                    const offset = i - (EMPTY_BRANDS.length - 1) / 2
                    return (
                        <div
                            key={preset}
                            style={{
                                transform: `translateX(${offset * -8}px) rotate(${offset * 7}deg)`,
                                zIndex: i === 2 ? 10 : 5 - Math.abs(offset),
                            }}
                            className="transition-transform duration-300 hover:-translate-y-1"
                        >
                            <BrandTile preset={preset} iconClassName="size-7" className="size-14 rounded-2xl shadow-sm" />
                        </div>
                    )
                })}
            </div>

            <h3 className="text-lg font-semibold tracking-tight">Connect your first identity provider</h3>
            <p className="mt-2 max-w-md text-sm text-muted-foreground">
                Let your team sign in with the accounts they already have — Microsoft, Google, GitHub, Okta and any
                OIDC or SAML provider. Endpoints come pre-filled for the popular ones.
            </p>

            <Button asChild className="mt-6">
                <Link to="/settings/providers/create">
                    Add Provider
                    <ArrowUpRight className="size-4" />
                </Link>
            </Button>
        </div>
    )
}

export function ProviderGridSkeleton() {
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex flex-col rounded-xl border bg-card p-5 shadow-sm">
                    <Skeleton className="size-12 rounded-xl" />
                    <Skeleton className="mt-4 h-5 w-32" />
                    <Skeleton className="mt-2 h-3 w-20" />
                    <div className="mt-5 flex items-center justify-between">
                        <Skeleton className="h-4 w-12" />
                        <Skeleton className="h-4 w-16" />
                    </div>
                </div>
            ))}
        </div>
    )
}
