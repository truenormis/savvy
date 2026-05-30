<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;

class CustomSamlPreset extends AbstractPreset
{
    public function key(): SsoPreset
    {
        return SsoPreset::CustomSaml;
    }

    public function fields(): array
    {
        return [
            ['key' => 'idp_entity_id', 'label' => 'IdP Entity ID', 'type' => 'text', 'required' => true, 'group' => 'config'],
            ['key' => 'idp_sso_url', 'label' => 'IdP SSO URL', 'type' => 'url', 'required' => true, 'group' => 'config'],
            ['key' => 'idp_x509_cert', 'label' => 'IdP X.509 certificate', 'type' => 'textarea', 'required' => true, 'group' => 'config'],
            ['key' => 'idp_x509_cert_standby', 'label' => 'Standby certificate (rotation)', 'type' => 'textarea', 'required' => false, 'group' => 'config'],
        ];
    }

    public function defaultClaimMappings(): array
    {
        return [
            'subject' => null, // NameID is used by default
            'email' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
            'name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
            'groups' => 'http://schemas.xmlsoap.org/claims/Group',
        ];
    }

    public function resolveConnection(IdentityProvider $provider): array
    {
        $certs = array_values(array_filter([
            $provider->config('idp_x509_cert'),
            $provider->config('idp_x509_cert_standby'),
        ]));

        return [
            'idp_entity_id' => $provider->config('idp_entity_id'),
            'idp_sso_url' => $provider->config('idp_sso_url'),
            'idp_x509_certs' => $certs,
        ];
    }
}
