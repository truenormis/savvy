<?php

namespace App\DTOs;

use App\Enums\TransactionType;

readonly class TransactionData
{
    public function __construct(
        public TransactionType $type,
        public int $accountId,
        public float $amount,
        public string $date,
        public ?int $toAccountId = null,
        public ?int $categoryId = null,
        public ?float $toAmount = null,
        public ?float $exchangeRate = null,
        public ?string $description = null,
        public ?array $items = null,
        public ?array $tagIds = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: TransactionType::from($data['type']),
            accountId: $data['account_id'],
            amount: $data['amount'],
            date: $data['date'],
            toAccountId: $data['to_account_id'] ?? null,
            categoryId: $data['category_id'] ?? null,
            toAmount: $data['to_amount'] ?? null,
            exchangeRate: $data['exchange_rate'] ?? null,
            description: $data['description'] ?? null,
            items: $data['items'] ?? null,
            tagIds: $data['tag_ids'] ?? null,
        );
    }

    public function hasItems(): bool
    {
        return !empty($this->items);
    }
}
