<?php

namespace App\Services\Sso\Connectors;

use App\DTOs\NormalizedIdentity;
use App\Exceptions\SsoException;
use App\Models\IdentityProvider;
use App\Services\Sso\Presets\PresetRegistry;
use App\Services\Sso\SsoStateStore;
use Illuminate\Support\Str;

abstract class AbstractConnector implements SsoConnector
{
    public function __construct(
        protected SsoStateStore $states,
        protected PresetRegistry $presets,
    ) {}

    protected function connection(IdentityProvider $provider): array
    {
        return $this->presets->get($provider->preset)->resolveConnection($provider);
    }

    protected function callbackUrl(IdentityProvider $provider): string
    {
        return route('sso.callback', ['slug' => $provider->slug]);
    }

    protected function acsUrl(IdentityProvider $provider): string
    {
        return route('sso.acs', ['slug' => $provider->slug]);
    }

    /**
     * Only allow same-origin relative paths to defend against open redirects.
     */
    protected function sanitizeRedirectAfter(?string $redirectAfter): ?string
    {
        if (! $redirectAfter) {
            return null;
        }

        if (! Str::startsWith($redirectAfter, '/') || Str::startsWith($redirectAfter, '//')) {
            return null;
        }

        return $redirectAfter;
    }

    /**
     * Map raw IdP claims/attributes to a NormalizedIdentity using the provider's
     * claim mappings (falling back to preset defaults). Overrides win — used by
     * OAuth2 to inject the verified primary email resolved out-of-band.
     *
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $overrides
     */
    protected function extractIdentity(IdentityProvider $provider, array $claims, array $overrides = []): NormalizedIdentity
    {
        $mappings = $provider->claim_mappings
            ?: $this->presets->get($provider->preset)->defaultClaimMappings();

        $read = function (string $key, mixed $default = null) use ($mappings, $claims) {
            $path = $mappings[$key] ?? null;

            if (blank($path)) {
                return $default;
            }

            foreach ((array) $path as $candidate) {
                $value = data_get($claims, $candidate);

                if (! blank($value)) {
                    return $value;
                }
            }

            return $default;
        };

        $subject = $overrides['subject'] ?? $read('subject');

        if (blank($subject)) {
            throw SsoException::make('missing_subject', 'Identity provider did not return a subject identifier.', 422);
        }

        $groupsPath = $mappings['groups'] ?? null;
        $groups = $groupsPath ? array_values((array) data_get($claims, $groupsPath, [])) : [];

        return new NormalizedIdentity(
            subject: (string) $subject,
            email: array_key_exists('email', $overrides) ? $overrides['email'] : $read('email'),
            emailVerified: array_key_exists('email_verified', $overrides)
                ? (bool) $overrides['email_verified']
                : filter_var($read('email_verified'), FILTER_VALIDATE_BOOLEAN),
            name: $read('name'),
            groups: $groups,
            raw: $claims,
        );
    }
}
