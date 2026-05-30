<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;

class KeycloakPreset extends AbstractOidcPreset
{
    public function key(): SsoPreset
    {
        return SsoPreset::Keycloak;
    }

    public function fields(): array
    {
        return [
            ['key' => 'base_url', 'label' => 'Keycloak base URL', 'type' => 'url', 'required' => true, 'group' => 'config', 'placeholder' => 'https://kc.example.com'],
            ['key' => 'realm', 'label' => 'Realm', 'type' => 'text', 'required' => true, 'group' => 'config'],
            ...$this->clientFields(),
        ];
    }

    protected function discoveryUrl(IdentityProvider $provider): string
    {
        $base = rtrim((string) $provider->config('base_url'), '/');
        $realm = $provider->config('realm');

        return "{$base}/realms/{$realm}/.well-known/openid-configuration";
    }
}
