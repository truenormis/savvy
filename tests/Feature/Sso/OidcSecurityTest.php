<?php

use App\Enums\UserRole;
use App\Models\IdentityProvider;
use App\Models\User;
use App\Services\Sso\JwksService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function b64url(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/** Drive the callback with a token built from the live nonce, return the response. */
function oidcCallback(string $pem, array $claimOverrides = [], string $kid = 'k1', ?array $jwks = null)
{
    $state = ssoStartFlow('idp');

    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => Http::response($jwks),
        'https://idp.example/token' => Http::response([
            'id_token' => ssoIdToken($pem, array_merge(['nonce' => $state->nonce], $claimOverrides), $kid),
            'access_token' => 'at',
        ]),
        'https://idp.example/userinfo' => Http::response(['sub' => 'sub-1']),
    ]);

    return test()->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}");
}

beforeEach(function () {
    ssoAdmin();
    [$this->pem, $this->jwks] = ssoKeypair('k1');
    // Only discovery is needed by the redirect step. JWKS/token are faked
    // per-test — Http::fake() appends and the FIRST matching stub wins, so a
    // pre-faked JWKS here would shadow per-test overrides.
    Http::fake([SSO_DISCOVERY => Http::response(ssoDiscovery())]);
    ssoOidcProvider();
});

it('puts PKCE S256, state and nonce on the authorization redirect', function () {
    $location = $this->get('/api/auth/sso/idp/redirect')->headers->get('Location');

    parse_str(parse_url($location, PHP_URL_QUERY), $q);
    expect($q['code_challenge_method'])->toBe('S256')
        ->and($q['code_challenge'])->not->toBeEmpty()
        ->and($q['state'])->not->toBeEmpty()
        ->and($q['nonce'])->not->toBeEmpty()
        ->and($q['redirect_uri'])->toContain('/auth/sso/idp/callback')
        ->and($q['scope'])->toBe('openid email');
});

it('accepts an audience array when azp pins the client', function () {
    $res = oidcCallback($this->pem, ['aud' => ['someone', 'client-x'], 'azp' => 'client-x'], jwks: $this->jwks);
    expect(ssoLocationParam($res, 'ticket'))->not->toBeNull();
});

it('rejects a multi-audience token without an azp', function () {
    $res = oidcCallback($this->pem, ['aud' => ['someone', 'client-x']], jwks: $this->jwks);
    expect(ssoLocationParam($res, 'error'))->not->toBeNull();
});

it('rejects a token whose azp is not the client', function () {
    $res = oidcCallback($this->pem, ['aud' => 'client-x', 'azp' => 'someone-else'], jwks: $this->jwks);
    expect(ssoLocationParam($res, 'error'))->not->toBeNull();
});

it('rejects an unsigned (alg:none) id_token', function () {
    $state = ssoStartFlow('idp');
    $none = b64url(json_encode(['alg' => 'none', 'typ' => 'JWT'])).'.'
        .b64url(json_encode(['iss' => 'https://idp.example', 'aud' => 'client-x', 'sub' => 'x', 'nonce' => $state->nonce, 'exp' => time() + 300])).'.';

    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => Http::response($this->jwks),
        'https://idp.example/token' => Http::response(['id_token' => $none, 'access_token' => 'at']),
    ]);

    $res = $this->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}");
    expect(ssoLocationParam($res, 'error'))->not->toBeNull();
    expect(User::count())->toBe(1); // only the seeded admin
});

it('rejects an HS256 token forged with the public key (alg confusion)', function () {
    $pub = openssl_pkey_get_details(openssl_pkey_get_private($this->pem))['key'];
    $state = ssoStartFlow('idp');
    $forged = JWT::encode(['iss' => 'https://idp.example', 'aud' => 'client-x', 'sub' => 'x', 'nonce' => $state->nonce, 'exp' => time() + 300], $pub, 'HS256', 'k1');

    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => Http::response($this->jwks),
        'https://idp.example/token' => Http::response(['id_token' => $forged, 'access_token' => 'at']),
    ]);

    expect(ssoLocationParam($this->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}"), 'error'))->not->toBeNull();
});

it('rejects an expired id_token', function () {
    $res = oidcCallback($this->pem, ['iat' => time() - 4000, 'exp' => time() - 3600], jwks: $this->jwks);
    expect(ssoLocationParam($res, 'error'))->not->toBeNull();
});

it('rejects an id_token from the wrong issuer', function () {
    $res = oidcCallback($this->pem, ['iss' => 'https://evil.example'], jwks: $this->jwks);
    expect(ssoLocationParam($res, 'error'))->not->toBeNull();
});

it('rejects when the token response carries no id_token', function () {
    $state = ssoStartFlow('idp');
    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => Http::response($this->jwks),
        'https://idp.example/token' => Http::response(['access_token' => 'at']),
    ]);

    expect(ssoLocationParam($this->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}"), 'error'))->not->toBeNull();
});

it('rejects when the token endpoint fails', function () {
    $state = ssoStartFlow('idp');
    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => Http::response($this->jwks),
        'https://idp.example/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    expect(ssoLocationParam($this->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}"), 'error'))->not->toBeNull();
});

it('rejects when the JWKS endpoint returns no keys', function () {
    $state = ssoStartFlow('idp');
    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => Http::response(['keys' => []]),
        'https://idp.example/token' => Http::response(['id_token' => ssoIdToken($this->pem, ['nonce' => $state->nonce]), 'access_token' => 'at']),
    ]);

    expect(ssoLocationParam($this->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}"), 'error'))->not->toBeNull();
});

it('picks up a rotated signing key via forced JWKS refresh', function () {
    [$pemNew, $jwksNew] = ssoKeypair('k1');   // the new signing key the token uses
    [, $jwksOld] = ssoKeypair('old-kid');     // stale keyset, missing kid "k1"
    $flip = (object) ['n' => 0];

    // One JWKS stub that serves the stale keyset first (priming the cache),
    // then the rotated keyset on the forced refetch.
    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => function () use ($jwksOld, $jwksNew, $flip) {
            return Http::response($flip->n++ === 0 ? $jwksOld : $jwksNew);
        },
    ]);

    app(JwksService::class)->keySet('https://idp.example/jwks'); // caches the stale keyset
    $state = ssoStartFlow('idp');

    Http::fake([
        'https://idp.example/token' => Http::response(['id_token' => ssoIdToken($pemNew, ['nonce' => $state->nonce, 'email' => 'rot@example.com'], 'k1'), 'access_token' => 'at']),
        'https://idp.example/userinfo' => Http::response(['sub' => 'sub-1']),
    ]);

    $res = $this->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}");
    expect(ssoLocationParam($res, 'ticket'))->not->toBeNull();
});

it('merges groups from userinfo into role mapping', function () {
    IdentityProvider::query()->update([
        'role_mapping' => [['claim' => 'groups', 'operator' => 'contains', 'value' => 'admins', 'role' => 'admin']],
    ]);

    $state = ssoStartFlow('idp');
    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => Http::response($this->jwks),
        // id_token has NO groups claim...
        'https://idp.example/token' => Http::response(['id_token' => ssoIdToken($this->pem, ['nonce' => $state->nonce, 'sub' => 'g1', 'email' => 'grp@example.com']), 'access_token' => 'at']),
        // ...groups come from userinfo.
        'https://idp.example/userinfo' => Http::response(['sub' => 'g1', 'groups' => ['admins']]),
    ]);

    $this->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}");
    expect(User::where('email', 'grp@example.com')->firstOrFail()->role)->toBe(UserRole::Admin);
});

it('prefers id_token claims over userinfo on conflict', function () {
    $state = ssoStartFlow('idp');
    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => Http::response($this->jwks),
        'https://idp.example/token' => Http::response(['id_token' => ssoIdToken($this->pem, ['nonce' => $state->nonce, 'sub' => 'p1', 'email' => 'real@example.com']), 'access_token' => 'at']),
        'https://idp.example/userinfo' => Http::response(['sub' => 'p1', 'email' => 'spoofed@example.com']),
    ]);

    $this->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}");
    expect(User::where('email', 'real@example.com')->exists())->toBeTrue();
    expect(User::where('email', 'spoofed@example.com')->exists())->toBeFalse();
});

it('rejects a state minted for a different provider', function () {
    ssoOidcProvider(['slug' => 'other', 'name' => 'Other']);
    $state = ssoStartFlow('other'); // state belongs to "other"

    Http::fake([
        SSO_DISCOVERY => Http::response(ssoDiscovery()),
        'https://idp.example/jwks' => Http::response($this->jwks),
        'https://idp.example/token' => Http::response(['id_token' => ssoIdToken($this->pem, ['nonce' => $state->nonce]), 'access_token' => 'at']),
    ]);

    // Feed "other"'s state to the "idp" callback.
    expect(ssoLocationParam($this->get("/api/auth/sso/idp/callback?code=abc&state={$state->state}"), 'error'))->not->toBeNull();
});

it('rejects a replayed state on a second callback', function () {
    $first = oidcCallback($this->pem, jwks: $this->jwks);
    expect(ssoLocationParam($first, 'ticket'))->not->toBeNull();

    // Reusing the now-consumed state must fail (state row was deleted).
    $state = \App\Models\SsoLoginState::query()->first();
    expect($state)->toBeNull();
});

it('errors on a callback missing code or state', function () {
    expect(ssoLocationParam($this->get('/api/auth/sso/idp/callback'), 'error'))->not->toBeNull();
});
