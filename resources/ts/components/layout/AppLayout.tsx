import { Outlet } from 'react-router-dom'
import { AppSidebar } from './Sidebar'
import { Header } from './Header'
import { ReadOnlyBanner } from './ReadOnlyBanner'
import { SidebarProvider, SidebarInset } from '@/components/ui/sidebar'
import { useUiStore } from '@/stores/ui'

export function AppLayout() {
    const sidebarOpen = useUiStore((state) => state.sidebarOpen)
    const setSidebarOpen = useUiStore((state) => state.setSidebarOpen)

    return (
        <SidebarProvider open={sidebarOpen} onOpenChange={setSidebarOpen}>
            <AppSidebar />
            <SidebarInset>
                <ReadOnlyBanner />
                <Header />
                <main className="flex-1 overflow-y-auto p-6">
                    <Outlet />
                </main>
            </SidebarInset>
        </SidebarProvider>
    )
}
