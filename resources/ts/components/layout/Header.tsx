import { SidebarTrigger } from '@/components/ui/sidebar'
import { Button } from '@/components/ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Moon, Sun, Wallet, Plus, ArrowDownLeft, ArrowUpRight, ArrowLeftRight, LogOut } from 'lucide-react'
import { Separator } from '@/components/ui/separator'
import { useTheme } from '@/hooks/use-theme'
import { useTotalBalance } from '@/hooks'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { getUserAvatarUrl, getUserInitials } from '@/lib/avatar'
export function Header() {
    const { theme, toggleTheme } = useTheme()
    const { data: balance } = useTotalBalance()
    const navigate = useNavigate()
    const { user, logout } = useAuthStore()

    const handleCreateTransaction = (type: 'income' | 'expense' | 'transfer') => {
        navigate(`/transactions/create?type=${type}`)
    }

    const handleLogout = () => {
        logout()
        navigate('/login')
    }

    return (
        <header className="sticky top-0 z-50 h-14 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div className="flex h-full items-center justify-between px-4">
                <div className="flex items-center gap-2">
                    <SidebarTrigger />
                    <Separator orientation="vertical" className="h-4" />
                </div>

                <div className="flex items-center gap-3">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button size="sm" className="gap-1">
                                <Plus className="size-4" />
                                <span className="hidden sm:inline">Transaction</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => handleCreateTransaction('income')}>
                                <ArrowDownLeft className="size-4 mr-2 text-green-600" />
                                Income
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => handleCreateTransaction('expense')}>
                                <ArrowUpRight className="size-4 mr-2 text-red-600" />
                                Expense
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => handleCreateTransaction('transfer')}>
                                <ArrowLeftRight className="size-4 mr-2 text-blue-600" />
                                Transfer
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    {balance && (
                        <div className="flex items-center gap-2 text-sm">
                            <Wallet className="size-4 text-muted-foreground" />
                            <span className="font-mono font-medium">
                                {(balance.total_balance ?? 0).toFixed(balance.decimals ?? 2)} {balance.currency}
                            </span>
                        </div>
                    )}

                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={toggleTheme}
                        aria-label="Toggle theme"
                    >
                        {theme === 'dark' ? (
                            <Sun className="h-5 w-5" />
                        ) : (
                            <Moon className="h-5 w-5" />
                        )}
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            {user && (
                            <Button variant="ghost" size="icon" className="rounded-full">
                                <Avatar className="size-8">
                                    <AvatarImage src={getUserAvatarUrl(user)} />
                                    <AvatarFallback>{getUserInitials(user)}</AvatarFallback>
                                </Avatar>
                            </Button>
                        )}
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            {user && (
                            <div className="flex items-center gap-2 px-2 py-1.5">
                                <Avatar className="size-8">
                                    <AvatarImage src={getUserAvatarUrl(user)} />
                                    <AvatarFallback>{getUserInitials(user)}</AvatarFallback>
                                </Avatar>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium truncate">{user.name}</p>
                                    <p className="text-xs text-muted-foreground truncate">{user.role}</p>
                                </div>
                            </div>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={handleLogout}>
                                <LogOut className="size-4 mr-2" />
                                Logout
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>
    )
}
