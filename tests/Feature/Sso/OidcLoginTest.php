<?php

use App\Enums\UserRole;
use App\Models\IdentityProvider;
use App\Models\SsoLoginState;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const DISCOVERY_URL = 'https://idp.test/.well-known/openid-configuration';

function oidcDiscovery(): array
{
    return [
        'issuer' => 'https://idp.test',
        'authorization_endpoint' => 'https://idp.test/auth',
        'token_endpoint' => 'https://idp.test/token',
        'jwks_uri' => 'https://idp.test/jwks',
        'userinfo_endpoint' => 'https://idp.test/userinfo',
    ];
}

function rsaKeypair(): array
{
    $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($res, $privatePem);
    $details = openssl_pkey_get_details($res);
    $b64 = fn ($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

    $jwks = ['keys' => [[
        'kty' => 'RSA',
        'kid' => 'test-key',
        'use' => 'sig',
        'alg' => 'RS256',
        'n' => $b64($details['rsa']['n']),
        'e' => $b64($details['rsa']['e']),
    ]]];

    return [$privatePem, $jwks];
}

function idToken(string $privatePem, string $nonce, array $overrides = []): string
{
    return JWT::encode(array_merge([
        'iss' => 'https://idp.test',
        'aud' => 'client123',
        'sub' => 'oidc-sub-1',
        'email' => 'oidc@test.com',
        'email_verified' => true,
        'name' => 'OIDC User',
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
    ], $overrides), $privatePem, 'RS256', 'test-key');
}

function oidcProvider(): IdentityProvider
{
    return IdentityProvider::create([
        'name' => 'Keycloak',
        'slug' => 'kc',
        'protocol' => 'oidc',
        'preset' => 'custom_oidc',
        'enabled' => true,
        'config' => ['discovery_url' => DISCOVERY_URL, 'client_id' => 'client123', 'scopes' => ['openid', 'email']],
        'secrets' => ['client_secret' => 'shh'],
        'allow_jit' => true,
        'link_by_email' => true,
    ]);
}

beforeEach(function () {
    User::create(['name' => 'Root', 'email' => 'root@test.com', 'password' => 'x', 'role' => UserRole::Admin]);
    oidcProvider();
});

it('completes the OIDC authorization-code flow', function () {
    [$privatePem, $jwks] = rsaKeypair();

    Http::fake([
        DISCOVERY_URL => Http::response(oidcDiscovery()),
        'https://idp.test/jwks' => Http::response($jwks),
    ]);

    $this->get('/api/auth/sso/kc/redirect')->assertRedirect();
    $state = SsoLoginState::firstOrFail();

    Http::fake([
        DISCOVERY_URL => Http::response(oidcDiscovery()),
        'https://idp.test/jwks' => Http::response($jwks),
        'https://idp.test/token' => Http::response([
            'id_token' => idToken($privatePem, $state->nonce),
            'access_token' => 'at',
            'token_type' => 'Bearer',
        ]),
        'https://idp.test/userinfo' => Http::response(['sub' => 'oidc-sub-1', 'email' => 'oidc@test.com']),
    ]);

    $location = $this->get("/api/auth/sso/kc/callback?code=abc&state={$state->state}")->headers->get('Location');
    expect($location)->toContain('ticket=');

    parse_str(parse_url($location, PHP_URL_QUERY), $q);
    $this->postJson('/api/auth/sso/exchange', ['ticket' => $q['ticket']])
        ->assertOk()
        ->assertJsonPath('user.email', 'oidc@test.com');
});

it('verifies the id_token when the JWKS omits the optional alg member', function () {
    [$privatePem, $jwks] = rsaKeypair();
    unset($jwks['keys'][0]['alg']);

    Http::fake([
        DISCOVERY_URL => Http::response(oidcDiscovery()),
        'https://idp.test/jwks' => Http::response($jwks),
    ]);

    $this->get('/api/auth/sso/kc/redirect')->assertRedirect();
    $state = SsoLoginState::firstOrFail();

    Http::fake([
        DISCOVERY_URL => Http::response(oidcDiscovery()),
        'https://idp.test/jwks' => Http::response($jwks),
        'https://idp.test/token' => Http::response([
            'id_token' => idToken($privatePem, $state->nonce),
            'access_token' => 'at',
            'token_type' => 'Bearer',
        ]),
        'https://idp.test/userinfo' => Http::response(['sub' => 'oidc-sub-1', 'email' => 'oidc@test.com']),
    ]);

    $location = $this->get("/api/auth/sso/kc/callback?code=abc&state={$state->state}")->headers->get('Location');
    expect($location)->toContain('ticket=');

    parse_str(parse_url($location, PHP_URL_QUERY), $q);
    $this->postJson('/api/auth/sso/exchange', ['ticket' => $q['ticket']])
        ->assertOk()
        ->assertJsonPath('user.email', 'oidc@test.com');
});

it('rejects an id_token with a mismatched nonce', function () {
    [$privatePem, $jwks] = rsaKeypair();

    Http::fake([
        DISCOVERY_URL => Http::response(oidcDiscovery()),
        'https://idp.test/jwks' => Http::response($jwks),
    ]);

    $this->get('/api/auth/sso/kc/redirect')->assertRedirect();
    $state = SsoLoginState::firstOrFail();

    Http::fake([
        DISCOVERY_URL => Http::response(oidcDiscovery()),
        'https://idp.test/jwks' => Http::response($jwks),
        'https://idp.test/token' => Http::response([
            'id_token' => idToken($privatePem, 'WRONG-NONCE'),
            'access_token' => 'at',
        ]),
    ]);

    expect($this->get("/api/auth/sso/kc/callback?code=abc&state={$state->state}")->headers->get('Location'))
        ->toContain('error=');
    expect(User::where('email', 'oidc@test.com')->exists())->toBeFalse();
});

it('rejects an id_token with the wrong audience', function () {
    [$privatePem, $jwks] = rsaKeypair();

    Http::fake([
        DISCOVERY_URL => Http::response(oidcDiscovery()),
        'https://idp.test/jwks' => Http::response($jwks),
    ]);

    $this->get('/api/auth/sso/kc/redirect')->assertRedirect();
    $state = SsoLoginState::firstOrFail();

    Http::fake([
        DISCOVERY_URL => Http::response(oidcDiscovery()),
        'https://idp.test/jwks' => Http::response($jwks),
        'https://idp.test/token' => Http::response([
            'id_token' => idToken($privatePem, $state->nonce, ['aud' => 'someone-else']),
            'access_token' => 'at',
        ]),
    ]);

    expect($this->get("/api/auth/sso/kc/callback?code=abc&state={$state->state}")->headers->get('Location'))
        ->toContain('error=');
});
