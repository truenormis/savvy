<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimals',
        'is_base',
        'rate',
    ];

    protected $casts = [
        'decimals' => 'integer',
        'is_base' => 'boolean',
        'rate' => 'decimal:6',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function scopeBase(Builder $query): Builder
    {
        return $query->where('is_base', true);
    }

    public function scopeNotBase(Builder $query): Builder
    {
        return $query->where('is_base', false);
    }

    public static function getBase(): ?self
    {
        return static::base()->first();
    }

    public function convertToBase(float $amount): float
    {
        if ($this->is_base) {
            return $amount;
        }

        return $amount * (float) $this->rate;
    }

    public function convertFromBase(float $amount): float
    {
        if ($this->is_base) {
            return $amount;
        }

        return $amount / (float) $this->rate;
    }

    public function convertTo(float $amount, Currency $targetCurrency): float
    {
        if ($this->id === $targetCurrency->id) {
            return $amount;
        }

        $baseAmount = $this->convertToBase($amount);

        return $targetCurrency->convertFromBase($baseAmount);
    }
}
