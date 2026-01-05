<?php

namespace App\Models;

use App\Enums\DebtType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'name',
        'type',
        'currency_id',
        'initial_balance',
        'is_active',
        'debt_type',
        'target_amount',
        'due_date',
        'is_paid_off',
        'counterparty',
        'debt_description',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'target_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_paid_off' => 'boolean',
        'debt_type' => DebtType::class,
        'due_date' => 'date',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes

    public function scopeRegularAccounts(Builder $query): Builder
    {
        return $query->whereIn('type', ['bank', 'crypto', 'cash']);
    }

    public function scopeDebts(Builder $query): Builder
    {
        return $query->where('type', 'debt');
    }

    public function scopeActiveDebts(Builder $query): Builder
    {
        return $query->where('type', 'debt')
            ->where('is_active', true)
            ->where('is_paid_off', false);
    }

    public function scopeIOwe(Builder $query): Builder
    {
        return $query->where('type', 'debt')->where('debt_type', 'i_owe');
    }

    public function scopeOwedToMe(Builder $query): Builder
    {
        return $query->where('type', 'debt')->where('debt_type', 'owed_to_me');
    }

    // Helper methods

    public function isDebt(): bool
    {
        return $this->type === 'debt';
    }

    public function isRegularAccount(): bool
    {
        return in_array($this->type, ['bank', 'crypto', 'cash']);
    }

    public function getCurrentBalanceAttribute(): float
    {
        if ($this->isDebt()) {
            return $this->calculateDebtBalance();
        }

        return $this->calculateRegularBalance();
    }

    private function calculateRegularBalance(): float
    {
        $income = $this->transactions()->where('type', 'income')->sum('amount');
        $expense = $this->transactions()->where('type', 'expense')->sum('amount');
        $transferOut = $this->transactions()->where('type', 'transfer')->sum('amount');
        $transferIn = Transaction::where('to_account_id', $this->id)->sum('to_amount');

        // Debt collection: money received from someone who owed you
        $debtCollectionIn = $this->transactions()
            ->where('type', 'debt_collection')
            ->sum('amount');

        // Debt payment: money paid to reduce your debt
        $debtPaymentOut = $this->transactions()
            ->where('type', 'debt_payment')
            ->sum('amount');

        return $this->initial_balance
            + $income
            - $expense
            - $transferOut
            + $transferIn
            + $debtCollectionIn
            - $debtPaymentOut;
    }

    private function calculateDebtBalance(): float
    {
        $targetAmount = (float) $this->target_amount;

        // Sum of all payments to this debt account
        $payments = Transaction::where('to_account_id', $this->id)->sum('to_amount');

        return $targetAmount - $payments;
    }

    public function getRemainingDebtAttribute(): float
    {
        return $this->isDebt() ? $this->current_balance : 0;
    }

    public function getPaymentProgressAttribute(): float
    {
        if (!$this->isDebt() || $this->target_amount <= 0) {
            return 0;
        }

        $paid = $this->target_amount - $this->current_balance;

        return min(100, round(($paid / $this->target_amount) * 100, 2));
    }

    public function checkAndMarkAsPaidOff(): bool
    {
        if (!$this->isDebt()) {
            return false;
        }

        if ($this->current_balance <= 0 && !$this->is_paid_off) {
            $this->update(['is_paid_off' => true]);

            return true;
        }

        return false;
    }
}
