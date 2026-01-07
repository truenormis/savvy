import { api } from './client'
import type { AutomationRule, AutomationRuleFormData, AutomationRuleLog, TriggerOption } from '@/types/automation'

const ENDPOINT = '/automation-rules'

export const automationApi = {
    getAll: () =>
        api.get<AutomationRule[]>(ENDPOINT),

    getById: (id: number | string) =>
        api.get<AutomationRule>(`${ENDPOINT}/${id}`),

    create: (data: AutomationRuleFormData) =>
        api.post<AutomationRule, AutomationRuleFormData>(ENDPOINT, data),

    update: (id: number | string, data: Partial<AutomationRuleFormData>) =>
        api.patch<AutomationRule, Partial<AutomationRuleFormData>>(`${ENDPOINT}/${id}`, data),

    delete: (id: number | string) =>
        api.delete<void>(`${ENDPOINT}/${id}`),

    toggle: (id: number | string) =>
        api.post<AutomationRule>(`${ENDPOINT}/${id}/toggle`),

    reorder: (rules: Array<{ id: number; priority: number }>) =>
        api.post<{ success: boolean }, { rules: Array<{ id: number; priority: number }> }>(`${ENDPOINT}/reorder`, { rules }),

    test: (id: number | string, transactionId: number) =>
        api.post<{ conditions_match: boolean; would_execute: boolean; actions: unknown[] }, { transaction_id: number }>(
            `${ENDPOINT}/${id}/test`,
            { transaction_id: transactionId }
        ),

    getLogs: (id: number | string) =>
        api.get<AutomationRuleLog[]>(`${ENDPOINT}/${id}/logs`),

    getTriggers: () =>
        api.get<TriggerOption[]>(`${ENDPOINT}/triggers`),
}
