<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = ['name', 'type', 'currency_id', 'initial_balance', 'is_active'];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getCurrentBalanceAttribute(): float
    {
        $income = $this->transactions()->where('type', 'income')->sum('amount');
        $expense = $this->transactions()->where('type', 'expense')->sum('amount');
        $transferOut = $this->transactions()->where('type', 'transfer')->sum('amount');
        $transferIn = Transaction::where('to_account_id', $this->id)->sum('to_amount');

        return $this->initial_balance + $income - $expense - $transferOut + $transferIn;
    }
}
