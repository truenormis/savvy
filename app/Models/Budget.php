<?php

namespace App\Models;

use App\Enums\BudgetPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Budget extends Model
{
    protected $fillable = [
        'name',
        'amount',
        'currency_id',
        'period',
        'start_date',
        'end_date',
        'is_global',
        'notify_at_percent',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period' => BudgetPeriod::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'is_global' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
