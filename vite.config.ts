import path from 'path'
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import pkg from './package.json'

export default defineConfig({
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
        __APP_VERSION__: JSON.stringify(`v${pkg.version}`),
    },
})
