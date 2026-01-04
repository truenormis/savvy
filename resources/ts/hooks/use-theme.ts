import { useState, useEffect } from 'react'

type Theme = 'light' | 'dark'

export function useTheme() {
    const [theme, setTheme] = useState<Theme>(() => {
        if (typeof window === 'undefined') return 'light'
        const stored = localStorage.getItem('theme') as Theme | null
        if (stored) return stored
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    })

    useEffect(() => {
        const root = document.documentElement
        root.classList.remove('light', 'dark')
        root.classList.add(theme)
        localStorage.setItem('theme', theme)
    }, [theme])

    const toggleTheme = () => {
        setTheme(prev => prev === 'dark' ? 'light' : 'dark')
    }

    return { theme, setTheme, toggleTheme }
}
