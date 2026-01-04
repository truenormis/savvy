<?php

namespace App\Builders;

use App\DTOs\TransactionFilterData;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class TransactionQueryBuilder
{
    private Builder $query;

    public function __construct()
    {
        $this->query = Transaction::query();
    }

    public static function make(): self
    {
        return new self();
    }

    public function withRelations(): self
    {
        $this->query->with(['account.currency', 'toAccount.currency', 'category', 'items', 'tags']);

        return $this;
    }

    public function withItemsCount(): self
    {
        $this->query->withCount('items');

        return $this;
    }

    public function applyFilters(TransactionFilterData $filters): self
    {
        if ($filters->type) {
            $this->query->where('type', $filters->type);
        }

        if ($filters->accountId) {
            $this->query->where(function ($q) use ($filters) {
                $q->where('account_id', $filters->accountId)
                    ->orWhere('to_account_id', $filters->accountId);
            });
        }

        if ($filters->categoryId) {
            $this->query->where('category_id', $filters->categoryId);
        }

        if (!empty($filters->categoryIds)) {
            $this->query->whereIn('category_id', $filters->categoryIds);
        }

        if (!empty($filters->tagIds)) {
            $this->query->whereHas('tags', function ($q) use ($filters) {
                $q->whereIn('tags.id', $filters->tagIds);
            });
        }

        if ($filters->startDate) {
            $this->query->where('date', '>=', $filters->startDate);
        }

        if ($filters->endDate) {
            $this->query->where('date', '<=', $filters->endDate);
        }

        if ($filters->minAmount) {
            $this->query->where('amount', '>=', $filters->minAmount);
        }

        if ($filters->maxAmount) {
            $this->query->where('amount', '<=', $filters->maxAmount);
        }

        if ($filters->search) {
            $this->query->where('description', 'like', "%{$filters->search}%");
        }

        return $this;
    }

    public function applySorting(TransactionFilterData $filters): self
    {
        if ($filters->sortBy === 'amount') {
            // Sort by amount converted to base currency
            $this->query
                ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                ->join('currencies', 'accounts.currency_id', '=', 'currencies.id')
                ->orderByRaw('transactions.amount * currencies.rate ' . ($filters->sortDirection === 'asc' ? 'ASC' : 'DESC'))
                ->select('transactions.*');
        } else {
            $this->query->orderBy($filters->sortBy, $filters->sortDirection);
        }

        return $this;
    }

    public function paginate(int $perPage)
    {
        return $this->query->paginate($perPage);
    }

    public function get()
    {
        return $this->query->get();
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }
}
