<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'accountId' => $this->account_id,
            'toAccountId' => $this->to_account_id,
            'categoryId' => $this->category_id,
            'amount' => (float) $this->amount,
            'toAmount' => $this->to_amount ? (float) $this->to_amount : null,
            'description' => $this->description,
            'frequency' => $this->frequency->value,
            'frequencyLabel' => $this->frequency->label(),
            'interval' => $this->interval,
            'dayOfWeek' => $this->day_of_week,
            'dayOfMonth' => $this->day_of_month,
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'nextRunDate' => $this->next_run_date->toDateString(),
            'lastRunDate' => $this->last_run_date?->toDateString(),
            'isActive' => $this->is_active,
            'account' => $this->whenLoaded('account', fn() => new AccountResource($this->account)),
            'toAccount' => $this->whenLoaded('toAccount', fn() => $this->toAccount ? new AccountResource($this->toAccount) : null),
            'category' => $this->whenLoaded('category', fn() => $this->category ? new CategoryResource($this->category) : null),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
