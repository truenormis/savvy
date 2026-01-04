<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => (float) $this->amount,
            'currencyId' => $this->currency_id,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'period' => $this->period->value,
            'periodLabel' => $this->period->label(),
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'isGlobal' => $this->is_global,
            'notifyAtPercent' => $this->notify_at_percent,
            'isActive' => $this->is_active,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'progress' => $this->whenHas('progress'),
        ];
    }
}
