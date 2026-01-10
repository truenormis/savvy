import { useState, useEffect } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { Home, FolderTree, Coins, CreditCard, Settings, ChevronDown, Receipt, PiggyBank, Hash, BarChart3, HandCoins, Users, Cog, Repeat, Zap, Shield, Upload, Database, LucideIcon, Github, ExternalLink } from 'lucide-react'
import { Logo } from '@/components/shared/Logo'
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
    SidebarFooter,
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { APP_VERSION } from '@/version'
interface MenuItem {
    to: string
    icon: LucideIcon
    label: string
}

const mainItems: MenuItem[] = [
    { to: '/', icon: Home, label: 'Dashboard' },
    { to: '/transactions', icon: Receipt, label: 'Transactions' },
    { to: '/recurring', icon: Repeat, label: 'Recurring' },
    { to: '/automation', icon: Zap, label: 'Automation' },
    { to: '/budgets', icon: PiggyBank, label: 'Budgets' },
    { to: '/debts', icon: HandCoins, label: 'Debts' },
    { to: '/reports', icon: BarChart3, label: 'Reports' },
]

const settingsItems: MenuItem[] = [
    { to: '/settings/system', icon: Cog, label: 'System' },
    { to: '/settings/security', icon: Shield, label: 'Security' },
    { to: '/settings/import', icon: Upload, label: 'Import' },
    { to: '/settings/backups', icon: Database, label: 'Backups' },
    { to: '/accounts', icon: CreditCard, label: 'Accounts' },
    { to: '/categories', icon: FolderTree, label: 'Categories' },
    { to: '/currencies', icon: Coins, label: 'Currencies' },
    { to: '/tags', icon: Hash, label: 'Tags' },
    { to: '/users', icon: Users, label: 'Users' },
]

export function AppSidebar() {
    const location = useLocation()
    const [settingsOpen, setSettingsOpen] = useState(false)
    const { setOpenMobile } = useSidebar()

    // Close mobile sidebar when navigating to a new page
    useEffect(() => {
        setOpenMobile(false)
    }, [location.pathname, setOpenMobile])

    const isActive = (path: string) => {
        if (path === '/') return location.pathname === '/'
        return location.pathname.startsWith(path)
    }

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <a href="/">
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                    <Logo className="size-5" />
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">Savvy</span>
                                    <span className="truncate text-xs text-muted-foreground">Finance Tracker</span>
                                </div>
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel>Menu</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {mainItems.map(({ to, icon: Icon, label }) => (
                                <SidebarMenuItem key={to}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isActive(to)}
                                        tooltip={label}
                                    >
                                        <NavLink to={to}>
                                            <Icon />
                                            <span>{label}</span>
                                        </NavLink>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <Collapsible open={settingsOpen} onOpenChange={setSettingsOpen} className="group/collapsible">
                                <SidebarMenuItem>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton tooltip="Settings">
                                            <Settings />
                                            <span>Settings</span>
                                            <ChevronDown className={`ml-auto transition-transform duration-200 ${settingsOpen ? '' : '-rotate-90'}`} />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {settingsItems.map(({ to, icon: Icon, label }) => (
                                                <SidebarMenuSubItem key={to}>
                                                    <SidebarMenuSubButton asChild isActive={isActive(to)}>
                                                        <NavLink to={to}>
                                                            <Icon />
                                                            <span>{label}</span>
                                                        </NavLink>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            ))}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton size="sm" className="text-xs text-muted-foreground justify-between">
                                    <span>Savvy</span>
                                    <span className="font-mono">{APP_VERSION}</span>
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent side="top" align="start" className="w-64">
                                <DropdownMenuLabel>About Savvy</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <div className="px-2 py-2 text-sm text-muted-foreground">
                                    <p>Self-hosted personal finance tracker. Track expenses, manage budgets, and take control of your money.</p>
                                </div>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <a
                                        href="https://github.com/truenormis/savvy"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="cursor-pointer"
                                    >
                                        <Github className="mr-2 size-4" />
                                        GitHub Repository
                                        <ExternalLink className="ml-auto size-3" />
                                    </a>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>

            <SidebarRail />
        </Sidebar>
    )
}
