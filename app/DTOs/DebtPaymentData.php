<?php

namespace App\DTOs;

readonly class DebtPaymentData
{
    public function __construct(
        public int $debtId,
        public int $accountId,
        public float $amount,
        public string $date,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            debtId: $data['debt_id'],
            accountId: $data['account_id'],
            amount: (float) $data['amount'],
            date: $data['date'],
            description: $data['description'] ?? null,
        );
    }
}
