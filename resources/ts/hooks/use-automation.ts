import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { automationApi } from '@/api/automation'
import type { AutomationRuleFormData } from '@/types/automation'
import { toast } from 'sonner'

const QUERY_KEY = ['automation-rules']

export function useAutomationRules() {
    return useQuery({
        queryKey: QUERY_KEY,
        queryFn: () => automationApi.getAll(),
    })
}

export function useAutomationRuleById(id: string | number) {
    return useQuery({
        queryKey: [...QUERY_KEY, id],
        queryFn: () => automationApi.getById(id),
        enabled: !!id,
    })
}

export function useAutomationTriggers() {
    return useQuery({
        queryKey: [...QUERY_KEY, 'triggers'],
        queryFn: () => automationApi.getTriggers(),
        staleTime: Infinity,
    })
}

export function useAutomationRuleLogs(id: string | number) {
    return useQuery({
        queryKey: [...QUERY_KEY, id, 'logs'],
        queryFn: () => automationApi.getLogs(id),
        enabled: !!id,
    })
}

export function useCreateAutomationRule(redirectTo?: string) {
    const queryClient = useQueryClient()
    const navigate = useNavigate()

    return useMutation({
        mutationFn: (data: AutomationRuleFormData) => automationApi.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Automation rule created')
            if (redirectTo) navigate(redirectTo)
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to create automation rule')
        },
    })
}

export function useUpdateAutomationRule(redirectTo?: string) {
    const queryClient = useQueryClient()
    const navigate = useNavigate()

    return useMutation({
        mutationFn: ({ id, data }: { id: string | number; data: Partial<AutomationRuleFormData> }) =>
            automationApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Automation rule updated')
            if (redirectTo) navigate(redirectTo)
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to update automation rule')
        },
    })
}

export function useDeleteAutomationRule() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (id: string | number) => automationApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Automation rule deleted')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to delete automation rule')
        },
    })
}

export function useToggleAutomationRule() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (id: string | number) => automationApi.toggle(id),
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success(`Rule ${data.is_active ? 'enabled' : 'disabled'}`)
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to toggle rule')
        },
    })
}

export function useReorderAutomationRules() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (rules: Array<{ id: number; priority: number }>) => automationApi.reorder(rules),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: QUERY_KEY })
            toast.success('Rules reordered')
        },
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to reorder rules')
        },
    })
}

export function useTestAutomationRule() {
    return useMutation({
        mutationFn: ({ ruleId, transactionId }: { ruleId: string | number; transactionId: number }) =>
            automationApi.test(ruleId, transactionId),
        onError: (error: Error) => {
            toast.error(error.message || 'Failed to test rule')
        },
    })
}
