import { Providers } from './providers'
import { RouterProvider } from 'react-router-dom'
import { NuqsAdapter } from 'nuqs/adapters/react-router/v7'
import { router } from './router'

export function App() {
    return (
        <Providers>
            <NuqsAdapter>
                <RouterProvider router={router} />
            </NuqsAdapter>
        </Providers>
    )
}
