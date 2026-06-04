<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;

class CustomOidcPreset extends AbstractOidcPreset
{
    public function key(): SsoPreset
    {
        return SsoPreset::CustomOidc;
    }

    public function fields(): array
    {
        return [
            ['key' => 'discovery_url', 'label' => 'Discovery URL', 'type' => 'url', 'required' => true, 'group' => 'config', 'placeholder' => 'https://idp.example.com/.well-known/openid-configuration'],
            ['key' => 'scopes', 'label' => 'Scopes (space-separated)', 'type' => 'text', 'required' => false, 'group' => 'config', 'placeholder' => 'openid profile email'],
            ...$this->clientFields(),
        ];
    }

    protected function discoveryUrl(IdentityProvider $provider): string
    {
        return (string) $provider->config('discovery_url');
    }
}
