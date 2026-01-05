// Format date as YYYY-MM (timezone-safe)
function formatYearMonth(date: Date): string {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    return `${year}-${month}`
}

// Generate months for the last 2 years
export function getMonthOptions() {
    const months = []
    const now = new Date()
    for (let i = 0; i < 24; i++) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1)
        const value = formatYearMonth(date)
        const label = date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
        months.push({ value, label })
    }
    return months
}

// Get current month in YYYY-MM format (timezone-safe)
export function getCurrentMonth(): string {
    return formatYearMonth(new Date())
}

// Generate quarters for the last 2 years
export function getQuarterOptions() {
    const quarters = []
    const now = new Date()
    const currentYear = now.getFullYear()
    const currentQuarter = Math.ceil((now.getMonth() + 1) / 3)

    for (let year = currentYear; year >= currentYear - 2; year--) {
        const maxQ = year === currentYear ? currentQuarter : 4
        for (let q = maxQ; q >= 1; q--) {
            quarters.push({
                value: `${year}-Q${q}`,
                label: `Q${q} ${year}`,
            })
        }
    }
    return quarters
}

// Generate years
export function getYearOptions() {
    const years = []
    const currentYear = new Date().getFullYear()
    for (let year = currentYear; year >= currentYear - 5; year--) {
        years.push({ value: year.toString(), label: year.toString() })
    }
    return years
}

// Format currency
export function formatCurrency(val: number) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(val)
}
