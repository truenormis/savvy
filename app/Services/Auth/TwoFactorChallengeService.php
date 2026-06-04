<?php

namespace App\Services\Auth;

use App\Models\TwoFactorChallenge;
use App\Models\User;
use Illuminate\Support\Str;

class TwoFactorChallengeService
{
    public function issue(User $user): string
    {
        $token = Str::random(48);

        TwoFactorChallenge::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($token),
            'expires_at' => now()->addMinutes((int) config('auth_session.challenge_ttl')),
        ]);

        return $token;
    }

    /**
     * Peek at a live challenge without consuming it, so a mistyped TOTP code
     * doesn't burn the challenge.
     */
    public function resolve(string $token): ?User
    {
        return TwoFactorChallenge::where('token_hash', $this->hash($token))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first()?->user;
    }

    /**
     * Atomically consume the challenge and return its user, or null.
     */
    public function consume(string $token): ?User
    {
        $hash = $this->hash($token);

        $affected = TwoFactorChallenge::where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        if ($affected !== 1) {
            return null;
        }

        return TwoFactorChallenge::where('token_hash', $hash)->first()?->user;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
