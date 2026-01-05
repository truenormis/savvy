import { useMemo } from 'react'
import { cn } from '@/lib/utils'

interface SparklineProps {
    data: number[]
    width?: number
    height?: number
    strokeWidth?: number
    className?: string
    color?: 'default' | 'success' | 'danger'
}

export function Sparkline({
    data,
    width = 80,
    height = 24,
    strokeWidth = 1.5,
    className,
    color = 'default',
}: SparklineProps) {
    const path = useMemo(() => {
        if (data.length < 2) return ''

        const min = Math.min(...data)
        const max = Math.max(...data)
        const range = max - min || 1

        const points = data.map((value, index) => {
            const x = (index / (data.length - 1)) * width
            const y = height - ((value - min) / range) * (height - 4) - 2
            return `${x},${y}`
        })

        return `M ${points.join(' L ')}`
    }, [data, width, height])

    const strokeColor = {
        default: 'stroke-muted-foreground',
        success: 'stroke-green-500',
        danger: 'stroke-red-500',
    }[color]

    if (data.length < 2) {
        return null
    }

    return (
        <svg
            width={width}
            height={height}
            className={cn('overflow-visible', className)}
        >
            <path
                d={path}
                fill="none"
                className={strokeColor}
                strokeWidth={strokeWidth}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}
