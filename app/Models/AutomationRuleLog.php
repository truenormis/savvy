<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rule_id',
        'trigger_entity_type',
        'trigger_entity_id',
        'actions_executed',
        'status',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'actions_executed' => 'array',
        'created_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }

    public function triggerEntity(): ?Model
    {
        if (!$this->trigger_entity_type || !$this->trigger_entity_id) {
            return null;
        }

        $modelClass = 'App\\Models\\' . $this->trigger_entity_type;

        if (!class_exists($modelClass)) {
            return null;
        }

        return $modelClass::find($this->trigger_entity_id);
    }

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            $log->created_at = $log->created_at ?? now();
        });
    }
}
