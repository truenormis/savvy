interface LogoProps {
    className?: string
}

export function Logo({ className = 'size-6' }: LogoProps) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 100 100"
            className={className}
            fill="none"
            stroke="currentColor"
        >
            {/* V down */}
            <path
                d="M15,28 L30,72 L45,28"
                strokeWidth="8"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            {/* V up */}
            <path
                d="M55,72 L70,28 L85,72"
                strokeWidth="8"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}
