import { CategoryType } from '@/types'
export { COLOR_OPTIONS, CATEGORY_COLORS } from './colors'

export const CATEGORY_TYPE_OPTIONS: { value: CategoryType; label: string }[] = [
    { value: 'income', label: 'Income' },
    { value: 'expense', label: 'Expense' },
]

export const ICON_OPTIONS = [
    '🏠', '🚗', '🍔', '💊', '🎬', '📚', '💰', '💳', '🛒', '✈️',
    '👕', '🎮', '📱', '💡', '🏥', '🎓', '🍺', '☕', '🎁', '💼',
]
