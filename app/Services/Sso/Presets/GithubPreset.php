<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;

class GithubPreset extends AbstractPreset
{
    public function key(): SsoPreset
    {
        return SsoPreset::Github;
    }

    public function fields(): array
    {
        return $this->clientFields();
    }

    public function defaultClaimMappings(): array
    {
        // GitHub user id is the stable subject (login is renamable). Email is
        // resolved out-of-band from /user/emails (primary + verified).
        return [
            'subject' => 'id',
            'email' => 'email',
            'name' => 'name',
            'groups' => null,
        ];
    }

    public function resolveConnection(IdentityProvider $provider): array
    {
        return [
            'authorize_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'userinfo_url' => 'https://api.github.com/user',
            'emails_url' => 'https://api.github.com/user/emails',
            'client_id' => $provider->config('client_id'),
            'scopes' => ['read:user', 'user:email'],
        ];
    }
}
