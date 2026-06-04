<?php

namespace App\Services\Auth\Webauthn;

use RuntimeException;

class WebauthnException extends RuntimeException
{
    public static function invalidChallenge(): self
    {
        return new self('Passkey challenge is invalid or has expired.');
    }

    public static function verificationFailed(): self
    {
        return new self('Passkey verification failed.');
    }

    public static function unknownCredential(): self
    {
        return new self('This passkey is not registered.');
    }

    public static function limitReached(int $max): self
    {
        return new self("You can register at most {$max} passkeys.");
    }
}
