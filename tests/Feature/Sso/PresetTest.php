<?php

use App\Models\IdentityProvider;
use App\Services\Sso\Presets\CustomOidcPreset;
use App\Services\Sso\Presets\EntraPreset;
use App\Services\Sso\Presets\OktaPreset;

function presetProvider(array $config): IdentityProvider
{
    $provider = new IdentityProvider;
    $provider->config = $config;

    return $provider;
}

it('substitutes {tenantid} with the token tid when matching the Entra issuer', function () {
    $preset = new EntraPreset;
    $disco = 'https://login.microsoftonline.com/{tenantid}/v2.0';

    expect($preset->matchesIssuer($disco, ['iss' => 'https://login.microsoftonline.com/abc-123/v2.0', 'tid' => 'abc-123']))->toBeTrue()
        ->and($preset->matchesIssuer($disco, ['iss' => 'https://login.microsoftonline.com/evil/v2.0', 'tid' => 'abc-123']))->toBeFalse();
});

it('matches a single-tenant Entra issuer strictly', function () {
    $preset = new EntraPreset;
    $disco = 'https://login.microsoftonline.com/abc-123/v2.0';

    expect($preset->matchesIssuer($disco, ['iss' => $disco]))->toBeTrue()
        ->and($preset->matchesIssuer($disco, ['iss' => 'https://login.microsoftonline.com/other/v2.0']))->toBeFalse();
});

it('falls back email to preferred_username and reads xms_edov for Entra', function () {
    $mappings = (new EntraPreset)->defaultClaimMappings();

    expect($mappings['email'])->toBe(['email', 'preferred_username', 'upn'])
        ->and($mappings['email_verified'])->toBe('xms_edov');
});

it('always forces openid into the resolved scopes', function () {
    $conn = (new CustomOidcPreset)->resolveConnection(presetProvider([
        'discovery_url' => 'https://idp.example/.well-known/openid-configuration',
        'client_id' => 'c',
        'scopes' => ['profile', 'email'],
    ]));

    expect($conn['scopes'])->toBe(['openid', 'profile', 'email']);
});

it('uses the Okta org authorization server discovery by default', function () {
    $conn = (new OktaPreset)->resolveConnection(presetProvider(['domain' => 'example.okta.com', 'client_id' => 'c']));

    expect($conn['discovery_url'])->toBe('https://example.okta.com/.well-known/openid-configuration');
});

it('uses an Okta custom authorization server discovery when configured', function () {
    $conn = (new OktaPreset)->resolveConnection(presetProvider([
        'domain' => 'example.okta.com',
        'auth_server_id' => 'aus1a2b3c',
        'client_id' => 'c',
    ]));

    expect($conn['discovery_url'])->toBe('https://example.okta.com/oauth2/aus1a2b3c/.well-known/openid-configuration');
});
