<?php

use App\Enums\UserRole;
use App\Models\IdentityProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an admin create an OIDC provider without leaking the secret', function () {
    $response = callAs('POST', '/api/identity-providers', [
        'name' => 'Company Okta',
        'slug' => 'company-okta',
        'preset' => 'okta',
        'enabled' => true,
        'fields' => [
            'domain' => 'example.okta.com',
            'client_id' => 'cid',
            'client_secret' => 'shh',
        ],
    ], ssoUser(UserRole::Admin));

    $response->assertCreated();
    $response->assertJsonPath('protocol', 'oidc');
    $response->assertJsonPath('hasClientSecret', true);
    expect($response->json())->not->toHaveKey('secrets');
    expect(json_encode($response->json()))->not->toContain('shh');

    $provider = IdentityProvider::first();
    expect($provider->secret('client_secret'))->toBe('shh');
    expect($provider->config('client_id'))->toBe('cid');
});

it('rejects non-admins', function () {
    callAs('POST', '/api/identity-providers', [
        'name' => 'X', 'slug' => 'x', 'preset' => 'google', 'fields' => [],
    ], ssoUser(UserRole::ReadWrite))->assertForbidden();
});

it('validates the slug format and uniqueness', function () {
    callAs('POST', '/api/identity-providers', [
        'name' => 'Bad', 'slug' => 'Not A Slug', 'preset' => 'google', 'fields' => [],
    ], ssoUser(UserRole::Admin))->assertStatus(422);

    IdentityProvider::create(['name' => 'G', 'slug' => 'google', 'protocol' => 'oidc', 'preset' => 'google']);

    callAs('POST', '/api/identity-providers', [
        'name' => 'G2', 'slug' => 'google', 'preset' => 'google', 'fields' => ['client_id' => 'a', 'client_secret' => 'b'],
    ], ssoUser(UserRole::Admin))->assertStatus(422);
});

it('fails when a required preset field is missing', function () {
    callAs('POST', '/api/identity-providers', [
        'name' => 'Okta', 'slug' => 'okta', 'preset' => 'okta',
        'fields' => ['client_id' => 'cid'],
    ], ssoUser(UserRole::Admin))->assertStatus(422)->assertJsonPath('error', 'missing_field');
});

it('keeps the existing secret when updated with a blank secret field', function () {
    $provider = IdentityProvider::create([
        'name' => 'GH', 'slug' => 'gh', 'protocol' => 'oauth2', 'preset' => 'github',
        'config' => ['client_id' => 'cid'], 'secrets' => ['client_secret' => 'keepme'],
    ]);

    callAs('PATCH', "/api/identity-providers/{$provider->id}", [
        'name' => 'GH Renamed',
        'fields' => ['client_id' => 'cid', 'client_secret' => ''],
    ], ssoUser(UserRole::Admin))->assertOk()->assertJsonPath('name', 'GH Renamed');

    expect($provider->fresh()->secret('client_secret'))->toBe('keepme');
});

it('exposes the preset catalog to admins only', function () {
    callAs('GET', '/api/auth/sso/presets', [], ssoUser(UserRole::Admin))
        ->assertOk()
        ->assertJsonFragment(['key' => 'github'])
        ->assertJsonFragment(['key' => 'custom_saml']);

    callAs('GET', '/api/auth/sso/presets', [], ssoUser(UserRole::ReadOnly))->assertForbidden();
});
