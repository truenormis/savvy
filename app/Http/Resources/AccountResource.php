<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'currencyId' => $this->currency_id,
            'initialBalance' => (float) $this->initial_balance,
            'currentBalance' => (float) $this->current_balance,
            'isActive' => $this->is_active,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
