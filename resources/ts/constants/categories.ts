import { CategoryType } from '@/types'

export const CATEGORY_TYPE_OPTIONS: { value: CategoryType; label: string }[] = [
    { value: 'income', label: 'Income' },
    { value: 'expense', label: 'Expense' },
]

export const ICON_OPTIONS = [
    '🏠', '🚗', '🍔', '💊', '🎬', '📚', '💰', '💳', '🛒', '✈️',
    '👕', '🎮', '📱', '💡', '🏥', '🎓', '🍺', '☕', '🎁', '💼',
]

export const COLOR_OPTIONS = [
    '#EF4444', '#F97316', '#EAB308', '#22C55E',
    '#14B8A6', '#3B82F6', '#8B5CF6', '#EC4899',
    '#6366F1', '#84CC16', '#06B6D4', '#F43F5E',
]
