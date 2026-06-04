<?php

use App\Enums\UserRole;
use App\Models\IdentityProvider;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('parses a space-separated scopes string into an array', function () {
    callAs('POST', '/api/identity-providers', [
        'name' => 'Custom',
        'slug' => 'custom',
        'preset' => 'custom_oidc',
        'fields' => [
            'discovery_url' => 'https://idp.example/.well-known/openid-configuration',
            'client_id' => 'cid',
            'client_secret' => 'sec',
            'scopes' => 'openid profile email groups',
        ],
    ], ssoUser(UserRole::Admin))->assertCreated();

    expect(IdentityProvider::first()->config('scopes'))->toBe(['openid', 'profile', 'email', 'groups']);
});

it('never changes the preset or protocol on update', function () {
    $provider = IdentityProvider::create([
        'name' => 'G', 'slug' => 'g', 'protocol' => 'oidc', 'preset' => 'google',
        'config' => ['client_id' => 'a'], 'secrets' => ['client_secret' => 'b'],
    ]);

    callAs('PATCH', "/api/identity-providers/{$provider->id}", [
        'name' => 'G2',
        'preset' => 'github',
        'protocol' => 'saml',
    ], ssoUser(UserRole::Admin))->assertOk();

    $fresh = $provider->fresh();
    expect($fresh->preset->value)->toBe('google');
    expect($fresh->protocol->value)->toBe('oidc');
});

it('blocks deleting a provider that would orphan an SSO-only user', function () {
    $provider = IdentityProvider::create(['name' => 'P', 'slug' => 'p', 'protocol' => 'oidc', 'preset' => 'google']);
    $user = User::create(['name' => 'Sso', 'email' => 'sso@x.com', 'password' => 'x', 'role' => UserRole::ReadOnly, 'is_sso_only' => true]);
    UserIdentity::create(['user_id' => $user->id, 'identity_provider_id' => $provider->id, 'subject' => 's']);

    callAs('DELETE', "/api/identity-providers/{$provider->id}", [], ssoUser(UserRole::Admin))->assertStatus(422);
    expect(IdentityProvider::find($provider->id))->not->toBeNull();
});

it('allows deleting when the SSO-only user has another linked provider', function () {
    $p1 = IdentityProvider::create(['name' => 'P1', 'slug' => 'p1', 'protocol' => 'oidc', 'preset' => 'google']);
    $p2 = IdentityProvider::create(['name' => 'P2', 'slug' => 'p2', 'protocol' => 'oidc', 'preset' => 'okta']);
    $user = User::create(['name' => 'Sso', 'email' => 'sso@x.com', 'password' => 'x', 'role' => UserRole::ReadOnly, 'is_sso_only' => true]);
    UserIdentity::create(['user_id' => $user->id, 'identity_provider_id' => $p1->id, 'subject' => 's1']);
    UserIdentity::create(['user_id' => $user->id, 'identity_provider_id' => $p2->id, 'subject' => 's2']);

    callAs('DELETE', "/api/identity-providers/{$p1->id}", [], ssoUser(UserRole::Admin))->assertNoContent();
});

it('allows deleting when the linked user still has a password', function () {
    $provider = IdentityProvider::create(['name' => 'P', 'slug' => 'p', 'protocol' => 'oidc', 'preset' => 'google']);
    $user = User::create(['name' => 'Pw', 'email' => 'pw@x.com', 'password' => 'realpass', 'role' => UserRole::ReadWrite, 'is_sso_only' => false]);
    UserIdentity::create(['user_id' => $user->id, 'identity_provider_id' => $provider->id, 'subject' => 's']);

    callAs('DELETE', "/api/identity-providers/{$provider->id}", [], ssoUser(UserRole::Admin))->assertNoContent();
});

it('hides disabled providers from the public login list and blocks their redirect', function () {
    IdentityProvider::create(['name' => 'Off', 'slug' => 'off', 'protocol' => 'oidc', 'preset' => 'google', 'enabled' => false]);
    IdentityProvider::create(['name' => 'On', 'slug' => 'on', 'protocol' => 'oidc', 'preset' => 'google', 'enabled' => true]);

    $this->getJson('/api/auth/sso/providers')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['slug' => 'on']);

    expect(ssoLocationParam($this->get('/api/auth/sso/off/redirect'), 'sso_error'))->not->toBeNull();
});

it('reports a healthy OIDC connection from the test endpoint', function () {
    $provider = IdentityProvider::create([
        'name' => 'KC', 'slug' => 'kc', 'protocol' => 'oidc', 'preset' => 'custom_oidc',
        'config' => ['discovery_url' => 'https://idp.example/.well-known/openid-configuration', 'client_id' => 'c'],
    ]);

    Http::fake(['https://idp.example/.well-known/openid-configuration' => Http::response(ssoDiscovery())]);

    callAs('POST', "/api/identity-providers/{$provider->id}/test", [], ssoUser(UserRole::Admin))
        ->assertOk()
        ->assertJsonPath('status', 'ok');
});

it('reports an error from the test endpoint when discovery is unreachable', function () {
    $provider = IdentityProvider::create([
        'name' => 'KC', 'slug' => 'kc', 'protocol' => 'oidc', 'preset' => 'custom_oidc',
        'config' => ['discovery_url' => 'https://down.example/.well-known/openid-configuration', 'client_id' => 'c'],
    ]);

    Http::fake(['https://down.example/*' => Http::response('nope', 500)]);

    callAs('POST', "/api/identity-providers/{$provider->id}/test", [], ssoUser(UserRole::Admin))
        ->assertStatus(502)
        ->assertJsonPath('status', 'error');
});
