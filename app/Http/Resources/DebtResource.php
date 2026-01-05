<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => 'debt',
            'debtType' => $this->debt_type?->value,
            'debtTypeLabel' => $this->debt_type?->label(),
            'currencyId' => $this->currency_id,
            'targetAmount' => (float) $this->target_amount,
            'currentBalance' => (float) $this->current_balance,
            'remainingDebt' => (float) $this->remaining_debt,
            'paymentProgress' => (float) $this->payment_progress,
            'dueDate' => $this->due_date?->toDateString(),
            'counterparty' => $this->counterparty,
            'description' => $this->debt_description,
            'isPaidOff' => $this->is_paid_off,
            'isActive' => $this->is_active,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
