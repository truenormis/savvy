<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'type',
        'account_id',
        'to_account_id',
        'category_id',
        'amount',
        'to_amount',
        'exchange_rate',
        'description',
        'date',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'amount' => 'decimal:2',
        'to_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'transaction_tag');
    }

    public function isTransfer(): bool
    {
        return $this->type->isTransfer();
    }

    public function isIncome(): bool
    {
        return $this->type === TransactionType::Income;
    }

    public function isExpense(): bool
    {
        return $this->type === TransactionType::Expense;
    }
}
