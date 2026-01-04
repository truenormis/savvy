<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => (int) $this->quantity,
            'pricePerUnit' => (float) $this->price_per_unit,
            'totalPrice' => (float) $this->total_price,
        ];
    }
}
