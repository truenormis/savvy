<?php

use App\Enums\SsoPreset;
use App\Models\IdentityProvider;
use App\Services\Sso\ConnectorFactory;
use App\Services\Sso\Connectors\OAuth2Connector;
use App\Services\Sso\Connectors\OidcConnector;
use App\Services\Sso\Connectors\SamlConnector;
use App\Services\Sso\Presets\PresetRegistry;

it('registers every preset into the container-resolved registry', function () {
    $registry = app(PresetRegistry::class);

    expect(array_keys($registry->all()))
        ->toEqualCanonicalizing(SsoPreset::values());
});

it('resolves the registry and factory as shared singletons', function () {
    expect(app(PresetRegistry::class))->toBe(app(PresetRegistry::class))
        ->and(app(ConnectorFactory::class))->toBe(app(ConnectorFactory::class));
});

it('hands back the connector that declares the provider protocol', function (string $preset, string $connector) {
    $provider = new IdentityProvider;
    $provider->preset = $preset;
    $provider->protocol = SsoPreset::from($preset)->protocol();

    expect(app(ConnectorFactory::class)->for($provider))->toBeInstanceOf($connector);
})->with([
    'oidc' => ['google', OidcConnector::class],
    'oauth2' => ['github', OAuth2Connector::class],
    'saml' => ['custom_saml', SamlConnector::class],
]);
