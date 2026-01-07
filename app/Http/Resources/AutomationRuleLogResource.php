<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationRuleLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_id' => $this->rule_id,
            'trigger_entity_type' => $this->trigger_entity_type,
            'trigger_entity_id' => $this->trigger_entity_id,
            'actions_executed' => $this->actions_executed,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
