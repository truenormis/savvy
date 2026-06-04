<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->beforeEach(fn () => $this->withoutVite())->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| SSO test helpers (shared across tests/Feature/Sso)
|--------------------------------------------------------------------------
*/

const SSO_DISCOVERY = 'https://idp.example/.well-known/openid-configuration';

function ssoAdmin(): \App\Models\User
{
    return \App\Models\User::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'x',
        'role' => \App\Enums\UserRole::Admin,
    ]);
}

function ssoUser(\App\Enums\UserRole $role = \App\Enums\UserRole::Admin, ?string $email = null): \App\Models\User
{
    return \App\Models\User::create([
        'name' => 'T',
        'email' => $email ?? ($role->value.'-'.uniqid().'@example.com'),
        'password' => 'x',
        'role' => $role,
    ]);
}

/**
 * Make an authenticated JSON request as the given user via a real server-side
 * session cookie (the test cookie-jar does not attach cookies on the api group,
 * so we pass them straight into the kernel).
 */
function callAs(string $method, string $uri, array $data, \App\Models\User $user, bool $csrf = true): \Illuminate\Testing\TestResponse
{
    $issued = app(\App\Services\Auth\AuthSessionService::class)->issue($user, request());

    $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
    if ($csrf) {
        $server['HTTP_X_CSRF_TOKEN'] = $issued['csrf'];
    }

    return test()->call(
        $method,
        $uri,
        [],
        ['svy_session' => $issued['token']],
        [],
        $server,
        $data ? json_encode($data) : null,
    );
}

function ssoDiscovery(array $overrides = []): array
{
    return array_merge([
        'issuer' => 'https://idp.example',
        'authorization_endpoint' => 'https://idp.example/auth',
        'token_endpoint' => 'https://idp.example/token',
        'jwks_uri' => 'https://idp.example/jwks',
        'userinfo_endpoint' => 'https://idp.example/userinfo',
    ], $overrides);
}

/** @return array{0:string,1:array} private PEM + JWKS */
function ssoKeypair(string $kid = 'k1'): array
{
    $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($res, $pem);
    $d = openssl_pkey_get_details($res);
    $b64 = fn ($x) => rtrim(strtr(base64_encode($x), '+/', '-_'), '=');

    $jwks = ['keys' => [[
        'kty' => 'RSA', 'kid' => $kid, 'use' => 'sig', 'alg' => 'RS256',
        'n' => $b64($d['rsa']['n']), 'e' => $b64($d['rsa']['e']),
    ]]];

    return [$pem, $jwks];
}

function ssoIdToken(string $pem, array $claims = [], string $kid = 'k1'): string
{
    return \Firebase\JWT\JWT::encode(array_merge([
        'iss' => 'https://idp.example',
        'aud' => 'client-x',
        'sub' => 'sub-1',
        'email' => 'user@example.com',
        'email_verified' => true,
        'name' => 'User',
        'iat' => time(),
        'exp' => time() + 300,
    ], $claims), $pem, 'RS256', $kid);
}

function ssoOidcProvider(array $overrides = []): \App\Models\IdentityProvider
{
    return \App\Models\IdentityProvider::create(array_merge([
        'name' => 'IdP',
        'slug' => 'idp',
        'protocol' => 'oidc',
        'preset' => 'custom_oidc',
        'enabled' => true,
        'config' => ['discovery_url' => SSO_DISCOVERY, 'client_id' => 'client-x', 'scopes' => ['openid', 'email']],
        'secrets' => ['client_secret' => 'shh'],
        'default_role' => 'read-only',
        'allow_jit' => true,
        'link_by_email' => true,
    ], $overrides));
}

function ssoGithubProvider(array $overrides = []): \App\Models\IdentityProvider
{
    return \App\Models\IdentityProvider::create(array_merge([
        'name' => 'GitHub',
        'slug' => 'gh',
        'protocol' => 'oauth2',
        'preset' => 'github',
        'enabled' => true,
        'config' => ['client_id' => 'cid'],
        'secrets' => ['client_secret' => 'csec'],
        'claim_mappings' => ['subject' => 'id', 'email' => 'email', 'name' => 'name', 'groups' => null],
        'allow_jit' => true,
        'link_by_email' => true,
    ], $overrides));
}

/** Begin a flow and return the issued state row for the given provider slug. */
function ssoStartFlow(string $slug): \App\Models\SsoLoginState
{
    test()->get("/api/auth/sso/{$slug}/redirect")->assertRedirect();

    $providerId = \App\Models\IdentityProvider::where('slug', $slug)->value('id');

    return \App\Models\SsoLoginState::where('identity_provider_id', $providerId)->latest('id')->firstOrFail();
}

/** Extract a query param from a redirect response's Location header. */
function ssoLocationParam(\Illuminate\Testing\TestResponse $response, string $key): ?string
{
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $q);

    return $q[$key] ?? null;
}
