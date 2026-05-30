<?php

namespace App\Services\Sso\Presets;

use App\Models\IdentityProvider;

abstract class AbstractOidcPreset extends AbstractPreset
{
    /**
     * Build the OIDC discovery document URL from the provider's raw config.
     */
    abstract protected function discoveryUrl(IdentityProvider $provider): string;

    public function defaultScopes(): array
    {
        return ['openid', 'profile', 'email'];
    }

    public function defaultClaimMappings(): array
    {
        return [
            'subject' => 'sub',
            'email' => 'email',
            'email_verified' => 'email_verified',
            'name' => 'name',
            'groups' => 'groups',
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function matchesIssuer(string $discoveryIssuer, array $claims): bool
    {
        $actual = $claims['iss'] ?? null;

        return is_string($actual) && hash_equals($discoveryIssuer, $actual);
    }

    public function resolveConnection(IdentityProvider $provider): array
    {
        $scopes = $provider->config('scopes') ?: $this->defaultScopes();

        return [
            'discovery_url' => $this->discoveryUrl($provider),
            'client_id' => $provider->config('client_id'),
            'scopes' => array_values(array_unique(['openid', ...$scopes])),
        ];
    }
}
