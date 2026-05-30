<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;

class AuthentikPreset extends AbstractOidcPreset
{
    public function key(): SsoPreset
    {
        return SsoPreset::Authentik;
    }

    public function fields(): array
    {
        return [
            ['key' => 'base_url', 'label' => 'Authentik base URL', 'type' => 'url', 'required' => true, 'group' => 'config', 'placeholder' => 'https://auth.example.com'],
            ['key' => 'app_slug', 'label' => 'Application slug', 'type' => 'text', 'required' => true, 'group' => 'config'],
            ...$this->clientFields(),
        ];
    }

    protected function discoveryUrl(IdentityProvider $provider): string
    {
        $base = rtrim((string) $provider->config('base_url'), '/');
        $slug = $provider->config('app_slug');

        return "{$base}/application/o/{$slug}/.well-known/openid-configuration";
    }
}
