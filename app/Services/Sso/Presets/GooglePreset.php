<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;

class GooglePreset extends AbstractOidcPreset
{
    public function key(): SsoPreset
    {
        return SsoPreset::Google;
    }

    public function fields(): array
    {
        return $this->clientFields();
    }

    public function defaultClaimMappings(): array
    {
        return [
            'subject' => 'sub',
            'email' => 'email',
            'email_verified' => 'email_verified',
            'name' => 'name',
            'groups' => null,
        ];
    }

    protected function discoveryUrl(IdentityProvider $provider): string
    {
        return 'https://accounts.google.com/.well-known/openid-configuration';
    }
}
