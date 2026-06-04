import { useSyncExternalStore } from 'react'

type Theme = 'light' | 'dark'

const STORAGE_KEY = 'theme'

function readInitialTheme(): Theme {
    if (typeof document === 'undefined') return 'light'
    // The blocking inline script in the document head resolves and applies the
    // theme class before first paint; the DOM is the single source of truth.
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light'
}

let currentTheme: Theme = readInitialTheme()
const listeners = new Set<() => void>()

function applyTheme(theme: Theme) {
    const root = document.documentElement
    root.classList.toggle('dark', theme === 'dark')
    root.classList.toggle('light', theme === 'light')
    root.style.colorScheme = theme
    document
        .querySelector('meta[name="theme-color"]')
        ?.setAttribute('content', theme === 'dark' ? '#1a1a1a' : '#ffffff')
}

function notifyAll() {
    listeners.forEach((notify) => notify())
}

function setThemeGlobal(theme: Theme) {
    if (theme === currentTheme) return
    currentTheme = theme
    localStorage.setItem(STORAGE_KEY, theme)
    applyTheme(theme)
    notifyAll()
}

if (typeof window !== 'undefined') {
    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_KEY && (event.newValue === 'dark' || event.newValue === 'light')) {
            if (event.newValue !== currentTheme) {
                currentTheme = event.newValue
                applyTheme(event.newValue)
                notifyAll()
            }
        }
    })
}

function subscribe(notify: () => void): () => void {
    listeners.add(notify)
    return () => {
        listeners.delete(notify)
    }
}

function getSnapshot(): Theme {
    return currentTheme
}

export function useTheme() {
    const theme = useSyncExternalStore(subscribe, getSnapshot, () => 'light' as Theme)

    return {
        theme,
        setTheme: setThemeGlobal,
        toggleTheme: () => setThemeGlobal(currentTheme === 'dark' ? 'light' : 'dark'),
    }
}
