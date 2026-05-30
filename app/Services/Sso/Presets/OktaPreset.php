<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;
use Illuminate\Support\Str;

class OktaPreset extends AbstractOidcPreset
{
    public function key(): SsoPreset
    {
        return SsoPreset::Okta;
    }

    public function defaultScopes(): array
    {
        return ['openid', 'profile', 'email', 'groups'];
    }

    public function fields(): array
    {
        return [
            ['key' => 'domain', 'label' => 'Okta domain', 'type' => 'text', 'required' => true, 'group' => 'config', 'placeholder' => 'example.okta.com'],
            ['key' => 'auth_server_id', 'label' => 'Authorization server ID', 'type' => 'text', 'required' => false, 'group' => 'config', 'placeholder' => 'default (leave blank for the org server)'],
            ...$this->clientFields(),
        ];
    }

    protected function discoveryUrl(IdentityProvider $provider): string
    {
        $domain = rtrim((string) $provider->config('domain'), '/');
        $domain = Str::startsWith($domain, ['http://', 'https://']) ? $domain : "https://{$domain}";

        $authServerId = trim((string) $provider->config('auth_server_id'));

        if ($authServerId !== '') {
            return "{$domain}/oauth2/{$authServerId}/.well-known/openid-configuration";
        }

        return "{$domain}/.well-known/openid-configuration";
    }
}
