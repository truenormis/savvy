<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;

class GitlabPreset extends AbstractOidcPreset
{
    public function key(): SsoPreset
    {
        return SsoPreset::Gitlab;
    }

    public function defaultClaimMappings(): array
    {
        return [
            'subject' => 'sub',
            'email' => 'email',
            'email_verified' => 'email_verified',
            'name' => 'name',
            'groups' => 'groups_direct',
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'base_url', 'label' => 'GitLab base URL', 'type' => 'url', 'required' => true, 'group' => 'config', 'placeholder' => 'https://gitlab.com'],
            ...$this->clientFields(),
        ];
    }

    protected function discoveryUrl(IdentityProvider $provider): string
    {
        $base = rtrim((string) $provider->config('base_url'), '/');

        return "{$base}/.well-known/openid-configuration";
    }
}
