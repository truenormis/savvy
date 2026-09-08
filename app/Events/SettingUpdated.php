<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SettingUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
    ) {}
}
