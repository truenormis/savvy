<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'icon' => $this->icon,
            'color' => $this->color,
            'transactionsCount' => $this->whenCounted('transactions'),
            'totalAmount' => $this->whenHas('total_amount'),
        ];
    }
}
