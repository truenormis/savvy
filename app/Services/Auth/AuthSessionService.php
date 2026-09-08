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
            'rotated_at' => now(),
            'last_used_at' => now(),
            'idle_expires_at' => now()->addMinutes((int) config('auth_session.idle_ttl')),
            'absolute_expires_at' => now()->addMinutes((int) config('auth_session.absolute_ttl')),
        ]);

        return ['token' => $token, 'csrf' => $csrf, 'session' => $session];
    }

    public function resolve(string $token): ?AuthSession
    {
        $hash = $this->hash($token);

        $session = AuthSession::where('token_hash', $hash)
            ->orWhere(fn ($q) => $q->where('previous_token_hash', $hash)->where('previous_expires_at', '>', now()))
            ->first();

        return $session?->isActive() ? $session : null;
    }

    public function matchesCurrentToken(AuthSession $session, string $token): bool
    {
        return hash_equals($session->token_hash, $this->hash($token));
    }

    public function acceptsCsrf(AuthSession $session, string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }

        if (hash_equals($session->csrf, $candidate)) {
            return true;
        }

        return $session->hasLiveGraceWindow()
            && $session->previous_csrf !== null
            && hash_equals($session->previous_csrf, $candidate);
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
        if ($session->hasLiveGraceWindow()) {
            return false;
        }

        $since = $session->rotated_at ?? $session->created_at;

        return $since->addMinutes((int) config('auth_session.rotate_after'))->isPast();
    }

    /**
     * Issue a fresh token + csrf on the same session row, preserving the
     * absolute lifetime. The superseded pair stays valid for a short grace
     * window so requests already in flight are not rejected.
     *
     * Returns null when a concurrent request rotated the row first; that
     * request's response carries the new pair, and this one stays valid
     * through the grace window.
     *
     * @return array{token: string, csrf: string}|null
     */
    public function rotate(AuthSession $session): ?array
    {
        $token = Str::random(48);
        $csrf = Str::random(40);
        $now = now();

        $claimed = AuthSession::where('id', $session->getKey())
            ->where('token_hash', $session->token_hash)
            ->update([
                'previous_token_hash' => $session->token_hash,
                'previous_csrf' => $session->csrf,
                'previous_expires_at' => $now->copy()->addSeconds((int) config('auth_session.rotate_grace')),
                'token_hash' => $this->hash($token),
                'csrf' => $csrf,
                'rotated_at' => $now,
                'last_used_at' => $now,
                'idle_expires_at' => $now->copy()->addMinutes((int) config('auth_session.idle_ttl'))->min($session->absolute_expires_at),
                'updated_at' => $now,
            ]);

        if ($claimed === 0) {
            $session->refresh();

            return null;
        }

        $session->refresh();

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
