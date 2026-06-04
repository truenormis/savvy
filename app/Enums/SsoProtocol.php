<?php

namespace App\Enums;

enum SsoProtocol: string
{
    case Oidc = 'oidc';
    case OAuth2 = 'oauth2';
    case Saml = 'saml';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Oidc => 'OpenID Connect',
            self::OAuth2 => 'OAuth 2.0',
            self::Saml => 'SAML 2.0',
        };
    }

    public function usesIdToken(): bool
    {
        return $this === self::Oidc;
    }
}
