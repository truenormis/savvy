<?php

namespace App\DTOs;

readonly class NormalizedIdentity
{
    public function __construct(
        public string $subject,
        public ?string $email = null,
        public bool $emailVerified = false,
        public ?string $name = null,
        public array $groups = [],
        public array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            subject: (string) $data['subject'],
            email: $data['email'] ?? null,
            emailVerified: (bool) ($data['email_verified'] ?? false),
            name: $data['name'] ?? null,
            groups: $data['groups'] ?? [],
            raw: $data['raw'] ?? [],
        );
    }
}
