<?php

namespace App\Services\Sso;

use App\Models\IdentityProvider;
use App\Models\SsoLoginState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SsoStateStore
{
    public function issue(IdentityProvider $provider, array $attributes = []): string
    {
        $state = Str::random(48);

        SsoLoginState::create([
            'state' => $state,
            'identity_provider_id' => $provider->id,
            'nonce' => $attributes['nonce'] ?? null,
            'code_verifier' => $attributes['code_verifier'] ?? null,
            'saml_request_id' => $attributes['saml_request_id'] ?? null,
            'redirect_after' => $attributes['redirect_after'] ?? null,
            'expires_at' => now()->addSeconds(config('sso.state_ttl', 600)),
        ]);

        return $state;
    }

    public function attachSamlRequestId(string $state, string $requestId): void
    {
        SsoLoginState::where('state', $state)->update(['saml_request_id' => $requestId]);
    }

    /**
     * Single-use: returns the state and removes it, or null if missing/expired.
     */
    public function consume(string $state): ?SsoLoginState
    {
        return DB::transaction(function () use ($state) {
            $row = SsoLoginState::where('state', $state)->lockForUpdate()->first();

            if (! $row) {
                return null;
            }

            $row->delete();

            return $row->isExpired() ? null : $row;
        });
    }

    public function purgeExpired(): int
    {
        return SsoLoginState::where('expires_at', '<', now())->delete();
    }
}
