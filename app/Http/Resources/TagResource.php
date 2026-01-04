<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'transactionsCount' => $this->when(isset($this->transactions_count), $this->transactions_count),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
