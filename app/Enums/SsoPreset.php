<?php

namespace App\Enums;

enum SsoPreset: string
{
    case Entra = 'entra';
    case Github = 'github';
    case Google = 'google';
    case Okta = 'okta';
    case Gitlab = 'gitlab';
    case Keycloak = 'keycloak';
    case Authentik = 'authentik';
    case CustomOidc = 'custom_oidc';
    case CustomSaml = 'custom_saml';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Entra => 'Microsoft Entra ID',
            self::Github => 'GitHub',
            self::Google => 'Google',
            self::Okta => 'Okta',
            self::Gitlab => 'GitLab',
            self::Keycloak => 'Keycloak',
            self::Authentik => 'Authentik',
            self::CustomOidc => 'Custom OIDC',
            self::CustomSaml => 'Custom SAML',
        };
    }

    public function protocol(): SsoProtocol
    {
        return match ($this) {
            self::Github => SsoProtocol::OAuth2,
            self::CustomSaml => SsoProtocol::Saml,
            default => SsoProtocol::Oidc,
        };
    }
}
