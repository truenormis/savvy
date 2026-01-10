import path from 'path'
import { defineConfig, loadEnv } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')

    return {
        plugins: [
            laravel({
                input: ['resources/ts/main.tsx'],
                refresh: true,
            }),
            react(),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': path.resolve(__dirname, './resources/ts'),
            },
        },
        define: {
            __APP_VERSION__: JSON.stringify(env.APP_VERSION || process.env.APP_VERSION || 'dev'),
        },
    }
})
