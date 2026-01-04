<?php

namespace App\DTOs;

use App\Enums\TransactionType;

readonly class TransactionFilterData
{
    public function __construct(
        public ?TransactionType $type = null,
        public ?int $accountId = null,
        public ?int $categoryId = null,
        public ?array $categoryIds = null,
        public ?array $tagIds = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?float $minAmount = null,
        public ?float $maxAmount = null,
        public ?string $search = null,
        public string $sortBy = 'date',
        public string $sortDirection = 'desc',
        public int $perPage = 20,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: isset($data['type']) ? TransactionType::from($data['type']) : null,
            accountId: $data['account_id'] ?? null,
            categoryId: $data['category_id'] ?? null,
            categoryIds: $data['category_ids'] ?? null,
            tagIds: $data['tag_ids'] ?? null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            minAmount: $data['min_amount'] ?? null,
            maxAmount: $data['max_amount'] ?? null,
            search: $data['search'] ?? null,
            sortBy: $data['sort_by'] ?? 'date',
            sortDirection: $data['sort_direction'] ?? 'desc',
            perPage: $data['per_page'] ?? 20,
        );
    }
}
