import { useEffect } from 'react'

const APP_NAME = 'Savvy'

interface PageProps {
    title: string
    children: React.ReactNode
}

export function Page({ title, children }: PageProps) {
    useEffect(() => {
        document.title = title ? `${title} | ${APP_NAME}` : APP_NAME
        return () => {
            document.title = APP_NAME
        }
    }, [title])

    return <>{children}</>
}
