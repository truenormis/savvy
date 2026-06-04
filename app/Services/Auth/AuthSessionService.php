<?php

namespace App\Services\Auth;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthSessionService
{
    /**
     * Open a new server-side session and return the raw token + csrf to hand
     * to the browser (only their hashes/values are persisted server-side).
     *
     * @return array{token: string, csrf: string, session: AuthSession}
     */
    public function issue(User $user, Request $request): array
    {
        $token = Str::random(48);
        $csrf = Str::random(40);

        $session = AuthSession::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($token),
            'csrf' => $csrf,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'last_used_at' => now(),
            'idle_expires_at' => now()->addMinutes((int) config('auth_session.idle_ttl')),
            'absolute_expires_at' => now()->addMinutes((int) config('auth_session.absolute_ttl')),
        ]);

        return ['token' => $token, 'csrf' => $csrf, 'session' => $session];
    }

    public function resolve(string $token): ?AuthSession
    {
        $session = AuthSession::where('token_hash', $this->hash($token))->first();

        return $session?->isActive() ? $session : null;
    }

    /**
     * Slide the idle window forward (capped by the absolute lifetime).
     */
    public function touch(AuthSession $session): void
    {
        $idle = now()->addMinutes((int) config('auth_session.idle_ttl'));

        $session->forceFill([
            'last_used_at' => now(),
            'idle_expires_at' => $idle->min($session->absolute_expires_at),
        ])->save();
    }

    public function shouldRotate(AuthSession $session): bool
    {
        return $session->created_at->addMinutes((int) config('auth_session.rotate_after'))->isPast();
    }

    /**
     * Issue a fresh token + csrf on the same session row, preserving the
     * absolute lifetime. Returns the new raw token + csrf.
     *
     * @return array{token: string, csrf: string}
     */
    public function rotate(AuthSession $session): array
    {
        $token = Str::random(48);
        $csrf = Str::random(40);

        $session->forceFill([
            'token_hash' => $this->hash($token),
            'csrf' => $csrf,
            'last_used_at' => now(),
            'idle_expires_at' => now()->addMinutes((int) config('auth_session.idle_ttl'))->min($session->absolute_expires_at),
        ])->save();

        return ['token' => $token, 'csrf' => $csrf];
    }

    public function revoke(AuthSession $session): void
    {
        $session->forceFill(['revoked_at' => now()])->save();
    }

    public function purgeExpired(): int
    {
        return AuthSession::where('absolute_expires_at', '<', now())
            ->orWhere(fn ($q) => $q->whereNotNull('revoked_at')->where('revoked_at', '<', now()->subDay()))
            ->delete();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
