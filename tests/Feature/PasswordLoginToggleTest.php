<?php

use App\Enums\UserRole;
use App\Models\IdentityProvider;
use App\Models\User;
use App\Services\Auth\AuthSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function passwordUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'U',
        'email' => 'u@test.com',
        'password' => 'secret1',
        'role' => UserRole::ReadWrite,
    ], $overrides));
}

function enabledSsoProvider(array $overrides = []): IdentityProvider
{
    return IdentityProvider::create(array_merge([
        'name' => 'IdP',
        'slug' => 'idp',
        'protocol' => 'oidc',
        'preset' => 'custom_oidc',
        'enabled' => true,
        'config' => ['discovery_url' => 'https://idp.test', 'client_id' => 'c'],
        'default_role' => 'read-only',
    ], $overrides));
}

it('blocks password login when disabled and an enabled SSO provider exists', function () {
    passwordUser();
    enabledSsoProvider();
    settings()->set('password_login_enabled', false);

    $this->postJson('/api/auth/login', ['email' => 'u@test.com', 'password' => 'secret1'])
        ->assertStatus(422);
});

it('self-heals: password login works when disabled but no enabled SSO provider exists', function () {
    passwordUser();
    settings()->set('password_login_enabled', false);

    $this->postJson('/api/auth/login', ['email' => 'u@test.com', 'password' => 'secret1'])->assertOk();
});

it('self-heals: password login works when the only SSO provider is disabled', function () {
    passwordUser();
    enabledSsoProvider(['enabled' => false]);
    settings()->set('password_login_enabled', false);

    $this->postJson('/api/auth/login', ['email' => 'u@test.com', 'password' => 'secret1'])->assertOk();
});

it('reports the effective password_login_enabled flag in auth status', function () {
    passwordUser();
    enabledSsoProvider();

    settings()->set('password_login_enabled', false);
    $this->getJson('/api/auth/status')->assertOk()->assertJsonPath('password_login_enabled', false);

    settings()->set('password_login_enabled', true);
    $this->getJson('/api/auth/status')->assertJsonPath('password_login_enabled', true);
});

it('refuses to disable password login without an enabled SSO provider', function () {
    $issued = app(AuthSessionService::class)->issue(passwordUser(['role' => UserRole::Admin]), request());

    $this->call('PATCH', '/api/settings', ['password_login_enabled' => false], ['svy_session' => $issued['token']], [], ['HTTP_X_CSRF_TOKEN' => $issued['csrf']])
        ->assertStatus(422)
        ->assertJsonPath('error', 'sso_required');
});

it('allows disabling password login when an enabled SSO provider exists', function () {
    enabledSsoProvider();
    $issued = app(AuthSessionService::class)->issue(passwordUser(['role' => UserRole::Admin]), request());

    $this->call('PATCH', '/api/settings', ['password_login_enabled' => false], ['svy_session' => $issued['token']], [], ['HTTP_X_CSRF_TOKEN' => $issued['csrf']])
        ->assertOk();

    expect(settings('password_login_enabled'))->toBeFalse();
});
