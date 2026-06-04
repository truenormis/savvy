<?php

namespace App\Services\Auth\Webauthn;

use App\Models\User;
use App\Models\WebauthnChallenge;
use Illuminate\Support\Str;

class WebauthnChallengeService
{
    public function issue(?User $user, string $type, string $options): string
    {
        $token = Str::random(48);

        WebauthnChallenge::create([
            'user_id' => $user?->id,
            'token_hash' => $this->hash($token),
            'type' => $type,
            'options' => $options,
            'expires_at' => now()->addMinutes((int) config('webauthn.challenge_ttl')),
        ]);

        return $token;
    }

    public function consume(string $token, string $type): ?WebauthnChallenge
    {
        $hash = $this->hash($token);

        $affected = WebauthnChallenge::where('token_hash', $hash)
            ->where('type', $type)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        if ($affected !== 1) {
            return null;
        }

        return WebauthnChallenge::where('token_hash', $hash)->first();
    }

    public function purgeExpired(): int
    {
        return WebauthnChallenge::where('expires_at', '<', now())->delete();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
