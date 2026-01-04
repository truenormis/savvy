import { useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { Home, FolderTree, Coins, CreditCard, Settings, ChevronDown, Receipt, PiggyBank, Hash, BarChart3 } from 'lucide-react'
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
    SidebarFooter,
    SidebarRail,
} from '@/components/ui/sidebar'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { APP_VERSION } from '@/version'

const mainItems = [
    { to: '/', icon: Home, label: 'Dashboard' },
    { to: '/transactions', icon: Receipt, label: 'Transactions' },
    { to: '/budgets', icon: PiggyBank, label: 'Budgets' },
    { to: '/reports', icon: BarChart3, label: 'Reports' },
]

const settingsItems = [
    { to: '/accounts', icon: CreditCard, label: 'Accounts' },
    { to: '/categories', icon: FolderTree, label: 'Categories' },
    { to: '/currencies', icon: Coins, label: 'Currencies' },
    { to: '/tags', icon: Hash, label: 'Tags' },
]

export function AppSidebar() {
    const location = useLocation()
    const [settingsOpen, setSettingsOpen] = useState(false)

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

                <Collapsible open={settingsOpen} onOpenChange={setSettingsOpen}>
                    <SidebarGroup>
                        <CollapsibleTrigger asChild>
                            <SidebarGroupLabel className="cursor-pointer hover:bg-sidebar-accent rounded-md transition-colors">
                                <Settings className="size-3 mr-1" />
                                Settings
                                <ChevronDown className={`ml-auto size-4 transition-transform duration-200 ${settingsOpen ? '' : '-rotate-90'}`} />
                            </SidebarGroupLabel>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {settingsItems.map(({ to, icon: Icon, label }) => (
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
                        </CollapsibleContent>
                    </SidebarGroup>
                </Collapsible>
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="sm" className="text-xs text-muted-foreground justify-between">
                            <span>Savvy</span>
                            <span className="font-mono">{APP_VERSION}</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>

            <SidebarRail />
        </Sidebar>
    )
}
