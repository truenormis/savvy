<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'trigger_type' => $this->trigger_type->value,
            'trigger_label' => $this->trigger_type->label(),
            'priority' => $this->priority,
            'conditions' => $this->conditions,
            'actions' => $this->actions,
            'is_active' => $this->is_active,
            'stop_processing' => $this->stop_processing,
            'runs_count' => $this->runs_count,
            'last_run_at' => $this->last_run_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
