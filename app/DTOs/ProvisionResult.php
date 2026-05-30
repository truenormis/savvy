<?php

namespace App\DTOs;

use App\Models\User;

readonly class ProvisionResult
{
    public function __construct(
        public User $user,
        public bool $requiresTwoFactor,
    ) {}
}
