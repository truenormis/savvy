<?php

namespace App\Models;

use App\Enums\TriggerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'priority',
        'conditions',
        'actions',
        'is_active',
        'stop_processing',
        'runs_count',
        'last_run_at',
    ];

    protected $casts = [
        'trigger_type' => TriggerType::class,
        'priority' => 'integer',
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'stop_processing' => 'boolean',
        'runs_count' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationRuleLog::class, 'rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTrigger(Builder $query, TriggerType $trigger): Builder
    {
        return $query->where('trigger_type', $trigger->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc');
    }

    public function incrementRuns(): void
    {
        $this->increment('runs_count');
        $this->update(['last_run_at' => now()]);
    }
}
