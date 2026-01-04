<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'toAmount' => $this->when($this->to_amount !== null, (float) $this->to_amount),
            'exchangeRate' => $this->when($this->exchange_rate !== null, (float) $this->exchange_rate),
            'description' => $this->description,
            'date' => $this->date->format('Y-m-d'),
            'account' => new AccountResource($this->whenLoaded('account')),
            'toAccount' => new AccountResource($this->whenLoaded('toAccount')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'items' => TransactionItemResource::collection($this->whenLoaded('items')),
            'itemsCount' => $this->whenCounted('items'),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'createdAt' => $this->created_at,
        ];
    }
}
