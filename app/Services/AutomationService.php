<?php

namespace App\Services;

use App\Enums\TriggerType;
use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomationService
{

    public function process(TriggerType $trigger, Model $entity): array
    {
        $rules = AutomationRule::query()
            ->active()
            ->forTrigger($trigger)
            ->ordered()
            ->get();

        $executedRules = [];

        foreach ($rules as $rule) {
            try {
                if ($this->evaluateConditions($rule->conditions, $entity)) {
                    $actionsResult = $this->executeActions($rule->actions, $entity);

                    $this->logExecution($rule, $entity, 'success', $actionsResult);
                    $rule->incrementRuns();
                    $executedRules[] = $rule;

                    if ($rule->stop_processing) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $this->logExecution($rule, $entity, 'error', null, $e->getMessage());
                Log::error('Automation rule failed', [
                    'rule_id' => $rule->id,
                    'entity' => get_class($entity),
                    'entity_id' => $entity->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $executedRules;
    }

    public function evaluateConditions(array $conditionGroup, Model $entity): bool
    {
        $match = $conditionGroup['match'] ?? 'all';
        $conditions = $conditionGroup['conditions'] ?? [];

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $result = $this->evaluateCondition($condition, $entity);

            if ($match === 'any' && $result) {
                return true;
            }

            if ($match === 'all' && !$result) {
                return false;
            }
        }

        return $match === 'all';
    }

    private function evaluateCondition(array $condition, Model $entity): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? $condition['op'] ?? 'equals';
        $value = $condition['value'] ?? null;

        $entityValue = $this->getFieldValue($entity, $field);

        return match ($operator) {
            'equals' => $entityValue == $value,
            'not_equals' => $entityValue != $value,
            'in' => is_array($value) && in_array($entityValue, $value),
            'not_in' => is_array($value) && !in_array($entityValue, $value),
            'gt' => is_numeric($entityValue) && $entityValue > $value,
            'gte' => is_numeric($entityValue) && $entityValue >= $value,
            'lt' => is_numeric($entityValue) && $entityValue < $value,
            'lte' => is_numeric($entityValue) && $entityValue <= $value,
            'between' => is_array($value) && count($value) === 2
                && $entityValue >= $value[0] && $entityValue <= $value[1],
            'contains' => is_string($entityValue) && str_contains(strtolower($entityValue), strtolower($value)),
            'not_contains' => is_string($entityValue) && !str_contains(strtolower($entityValue), strtolower($value)),
            'starts_with' => is_string($entityValue) && str_starts_with(strtolower($entityValue), strtolower($value)),
            'ends_with' => is_string($entityValue) && str_ends_with(strtolower($entityValue), strtolower($value)),
            'matches' => is_string($entityValue) && preg_match('/' . $value . '/i', $entityValue),
            'is_null' => $entityValue === null,
            'is_not_null' => $entityValue !== null,
            'has_any' => $this->hasAnyTag($entity, $value),
            'has_all' => $this->hasAllTags($entity, $value),
            'has_none' => $this->hasNoTags($entity, $value),
            default => false,
        };
    }

    private function getFieldValue(Model $entity, string $field): mixed
    {
        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            $value = $entity;

            foreach ($parts as $part) {
                if ($value === null) {
                    return null;
                }

                $value = $value->{$part} ?? null;
            }

            return $value;
        }

        if ($field === 'type' && $entity instanceof Transaction) {
            return $entity->type->value;
        }

        return $entity->{$field} ?? null;
    }

    private function hasAnyTag(Model $entity, mixed $tagIds): bool
    {
        if (!method_exists($entity, 'tags') || !is_array($tagIds)) {
            return false;
        }

        $entityTagIds = $entity->tags->pluck('id')->toArray();

        return !empty(array_intersect($entityTagIds, $tagIds));
    }

    private function hasAllTags(Model $entity, mixed $tagIds): bool
    {
        if (!method_exists($entity, 'tags') || !is_array($tagIds)) {
            return false;
        }

        $entityTagIds = $entity->tags->pluck('id')->toArray();

        return empty(array_diff($tagIds, $entityTagIds));
    }

    private function hasNoTags(Model $entity, mixed $tagIds): bool
    {
        if (!method_exists($entity, 'tags') || !is_array($tagIds)) {
            return true;
        }

        $entityTagIds = $entity->tags->pluck('id')->toArray();

        return empty(array_intersect($entityTagIds, $tagIds));
    }

    public function executeActions(array $actions, Model $entity): array
    {
        $results = [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            $result = $this->executeAction($action, $entity);
            $results[] = [
                'type' => $type,
                'result' => $result,
            ];
        }

        return $results;
    }

    private function executeAction(array $action, Model $entity): mixed
    {
        $type = $action['type'] ?? '';

        return match ($type) {
            'set_category' => $this->actionSetCategory($action, $entity),
            'add_tags' => $this->actionAddTags($action, $entity),
            'remove_tags' => $this->actionRemoveTags($action, $entity),
            'set_description' => $this->actionSetDescription($action, $entity),
            'create_transfer' => $this->actionCreateTransfer($action, $entity),
            default => null,
        };
    }

    private function actionSetCategory(array $action, Model $entity): bool
    {
        if (!$entity instanceof Transaction) {
            return false;
        }

        $categoryId = $action['category_id'] ?? null;

        if ($categoryId === null) {
            return false;
        }

        $entity->update(['category_id' => $categoryId]);

        return true;
    }

    private function actionAddTags(array $action, Model $entity): bool
    {
        if (!method_exists($entity, 'tags')) {
            return false;
        }

        $tagIds = $action['tag_ids'] ?? [];

        if (empty($tagIds)) {
            return false;
        }

        $currentTagIds = $entity->tags->pluck('id')->toArray();
        $newTagIds = array_unique(array_merge($currentTagIds, $tagIds));
        $entity->tags()->sync($newTagIds);

        return true;
    }

    private function actionRemoveTags(array $action, Model $entity): bool
    {
        if (!method_exists($entity, 'tags')) {
            return false;
        }

        $tagIds = $action['tag_ids'] ?? [];

        if (empty($tagIds)) {
            return false;
        }

        $currentTagIds = $entity->tags->pluck('id')->toArray();
        $newTagIds = array_diff($currentTagIds, $tagIds);
        $entity->tags()->sync($newTagIds);

        return true;
    }

    private function actionSetDescription(array $action, Model $entity): bool
    {
        $template = $action['value'] ?? $action['template'] ?? $action['description'] ?? null;

        if ($template === null) {
            return false;
        }

        $description = $this->parseTemplate($template, $entity);
        $entity->update(['description' => $description]);

        return true;
    }

    private function actionCreateTransfer(array $action, Model $entity): ?int
    {
        if (!$entity instanceof Transaction) {
            return null;
        }

        $fromAccountId = $this->resolveValue($action['from_account_id'] ?? null, $entity);
        $toAccountId = $action['to_account_id'] ?? null;
        $amountFormula = $action['amount_formula'] ?? $action['amount'] ?? null;
        $description = $action['description'] ?? 'Auto-transfer by automation rule';

        if (!$fromAccountId || !$toAccountId || !$amountFormula) {
            return null;
        }

        $amount = $this->evaluateFormula($amountFormula, $entity);

        if ($amount <= 0) {
            return null;
        }

        $transfer = Transaction::create([
            'type' => 'transfer',
            'account_id' => $fromAccountId,
            'to_account_id' => $toAccountId,
            'amount' => $amount,
            'description' => $this->parseTemplate($description, $entity),
            'date' => now()->toDateString(),
        ]);

        return $transfer->id;
    }

    private function resolveValue(mixed $value, Model $entity): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (preg_match('/^\{\{(.+)\}\}$/', $value, $matches)) {
            $path = trim($matches[1]);

            if (str_starts_with($path, 'transaction.') || str_starts_with($path, 'entity.')) {
                $field = preg_replace('/^(transaction|entity)\./', '', $path);

                return $this->getFieldValue($entity, $field);
            }
        }

        return $value;
    }

    private function evaluateFormula(mixed $formula, Model $entity): float
    {
        if (is_numeric($formula)) {
            return (float) $formula;
        }

        if (!is_string($formula)) {
            return 0;
        }

        $formula = preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) use ($entity) {
            $path = trim($matches[1]);

            if (str_starts_with($path, 'transaction.') || str_starts_with($path, 'entity.')) {
                $field = preg_replace('/^(transaction|entity)\./', '', $path);
                $value = $this->getFieldValue($entity, $field);

                return is_numeric($value) ? $value : 0;
            }

            return 0;
        }, $formula);

        $formula = preg_replace('/[^0-9+\-*\/().\s]/', '', $formula);

        try {
            return (float) eval("return $formula;");
        } catch (\Throwable) {
            return 0;
        }
    }

    private function parseTemplate(string $template, Model $entity): string
    {
        return preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) use ($entity) {
            $path = trim($matches[1]);

            if (str_starts_with($path, 'transaction.') || str_starts_with($path, 'entity.')) {
                $field = preg_replace('/^(transaction|entity)\./', '', $path);

                return (string) $this->getFieldValue($entity, $field);
            }

            return $matches[0];
        }, $template);
    }

    private function logExecution(
        AutomationRule $rule,
        Model $entity,
        string $status,
        ?array $actionsExecuted = null,
        ?string $errorMessage = null
    ): void {
        AutomationRuleLog::create([
            'rule_id' => $rule->id,
            'trigger_entity_type' => class_basename($entity),
            'trigger_entity_id' => $entity->id,
            'actions_executed' => $actionsExecuted,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    public function testRule(AutomationRule $rule, Transaction $transaction): array
    {
        $conditionsMatch = $this->evaluateConditions($rule->conditions, $transaction);

        return [
            'conditions_match' => $conditionsMatch,
            'would_execute' => $conditionsMatch,
            'actions' => $rule->actions,
        ];
    }
}
