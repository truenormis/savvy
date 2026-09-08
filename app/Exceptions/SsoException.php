<?php

namespace App\Exceptions;

use RuntimeException;

class SsoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'sso_error',
        public readonly int $status = 400,
    ) {
        parent::__construct($message);
    }

    public static function make(string $errorCode, string $message, int $status = 400): self
    {
        return new self($message, $errorCode, $status);
    }
}
