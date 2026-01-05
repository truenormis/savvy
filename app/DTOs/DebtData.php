<?php

namespace App\DTOs;

use App\Enums\DebtType;

readonly class DebtData
{
    public function __construct(
        public string $name,
        public DebtType $debtType,
        public int $currencyId,
        public float $amount,
        public ?string $dueDate = null,
        public ?string $counterparty = null,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            debtType: $data['debt_type'] instanceof DebtType
                ? $data['debt_type']
                : DebtType::from($data['debt_type']),
            currencyId: $data['currency_id'],
            amount: (float) $data['amount'],
            dueDate: $data['due_date'] ?? null,
            counterparty: $data['counterparty'] ?? null,
            description: $data['description'] ?? null,
        );
    }
}
