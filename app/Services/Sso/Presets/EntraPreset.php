<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;

class EntraPreset extends AbstractOidcPreset
{
    public function key(): SsoPreset
    {
        return SsoPreset::Entra;
    }

    public function fields(): array
    {
        return [
            ['key' => 'tenant', 'label' => 'Tenant ID', 'type' => 'text', 'required' => true, 'group' => 'config', 'placeholder' => 'common / organizations / <tenant-guid>'],
            ...$this->clientFields(),
        ];
    }

    public function defaultClaimMappings(): array
    {
        return [
            'subject' => 'sub',
            'email' => ['email', 'preferred_username', 'upn'],
            'email_verified' => 'xms_edov',
            'name' => 'name',
            'groups' => 'groups',
        ];
    }

    public function matchesIssuer(string $discoveryIssuer, array $claims): bool
    {
        if (str_contains($discoveryIssuer, '{tenantid}') && ! empty($claims['tid'])) {
            $discoveryIssuer = str_replace('{tenantid}', (string) $claims['tid'], $discoveryIssuer);
        }

        return parent::matchesIssuer($discoveryIssuer, $claims);
    }

    protected function discoveryUrl(IdentityProvider $provider): string
    {
        $tenant = $provider->config('tenant', 'common');

        return "https://login.microsoftonline.com/{$tenant}/v2.0/.well-known/openid-configuration";
    }
}
