<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $algorithm = 'HS256';
    private int $ttl;

    public function __construct()
    {
        $this->secret = config('app.key');
        $this->ttl = (int) config('jwt.ttl', 60 * 24); // minutes, default 24h
    }

    public function encode(User $user): string
    {
        $payload = [
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + ($this->ttl * 60),
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Create a temporary token for 2FA verification (5 minutes TTL)
     */
    public function encodeTwoFactorToken(User $user): string
    {
        $payload = [
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + (5 * 60), // 5 minutes
            '2fa' => true,
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function decode(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (\Exception) {
            return null;
        }
    }

    public function getUserId(string $token): ?int
    {
        $payload = $this->decode($token);

        return $payload?->sub;
    }
}
