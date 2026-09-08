<?php

use App\DTOs\NormalizedIdentity;
use App\Enums\UserRole;
use App\Exceptions\SsoException;
use App\Models\IdentityProvider;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Sso\ProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function provider(array $overrides = []): IdentityProvider
{
    return IdentityProvider::create(array_merge([
        'name' => 'IdP',
        'slug' => 'idp',
        'protocol' => 'oidc',
        'preset' => 'custom_oidc',
        'enabled' => true,
        'config' => ['discovery_url' => 'https://idp.test', 'client_id' => 'c'],
        'default_role' => 'read-only',
        'allow_jit' => true,
        'link_by_email' => true,
    ], $overrides));
}

function seedAdmin(): User
{
    return User::create(['name' => 'Root', 'email' => 'root@test.com', 'password' => 'x', 'role' => UserRole::Admin]);
}

function identity(array $overrides = []): NormalizedIdentity
{
    return NormalizedIdentity::fromArray(array_merge([
        'subject' => 'ext-1',
        'email' => 'jane@test.com',
        'email_verified' => true,
        'name' => 'Jane',
        'groups' => [],
        'raw' => [],
    ], $overrides));
}

beforeEach(fn () => $this->service = app(ProvisioningService::class));

it('links a verified email to an existing user', function () {
    seedAdmin();
    $existing = User::create(['name' => 'Jane', 'email' => 'jane@test.com', 'password' => 'x', 'role' => UserRole::ReadWrite]);
    $p = provider();

    $result = $this->service->resolve($p, identity());

    expect($result->user->id)->toBe($existing->id);
    expect(UserIdentity::where('user_id', $existing->id)->where('identity_provider_id', $p->id)->exists())->toBeTrue();
});

it('refuses to link an unverified email to an existing user', function () {
    seedAdmin();
    $existing = User::create(['name' => 'Jane', 'email' => 'jane@test.com', 'password' => 'x', 'role' => UserRole::ReadWrite]);
    $p = provider();

    expect(fn () => $this->service->resolve($p, identity(['email_verified' => false])))
        ->toThrow(SsoException::class);

    expect(UserIdentity::where('user_id', $existing->id)->where('identity_provider_id', $p->id)->exists())->toBeFalse();
});

it('JIT-provisions a new user with a mapped role', function () {
    seedAdmin();
    $p = provider(['role_mapping' => [['claim' => 'groups', 'operator' => 'contains', 'value' => 'admins', 'role' => 'admin']]]);

    $result = $this->service->resolve($p, identity(['email' => 'new@test.com', 'groups' => ['admins']]));

    expect($result->user->email)->toBe('new@test.com');
    expect($result->user->role)->toBe(UserRole::Admin);
    expect($result->user->is_sso_only)->toBeTrue();
});

it('refuses JIT sign-up for an unverified email when verification is required', function () {
    seedAdmin();
    settings()->set('sso_require_verified_email', true);
    $p = provider();

    expect(fn () => $this->service->resolve($p, identity(['email' => 'new@test.com', 'email_verified' => false])))
        ->toThrow(SsoException::class);

    expect(User::where('email', 'new@test.com')->exists())->toBeFalse();
});

it('allows JIT sign-up for a verified email when verification is required', function () {
    seedAdmin();
    settings()->set('sso_require_verified_email', true);
    $p = provider();

    $result = $this->service->resolve($p, identity(['email' => 'new@test.com', 'email_verified' => true]));

    expect($result->user->email)->toBe('new@test.com');
});

it('allows JIT sign-up for an unverified email when verification is not required', function () {
    seedAdmin();
    $p = provider();

    $result = $this->service->resolve($p, identity(['email' => 'new@test.com', 'email_verified' => false]));

    expect($result->user->email)->toBe('new@test.com');
});

it('uses the default role when no mapping matches', function () {
    seedAdmin();
    $p = provider(['default_role' => 'read-write']);

    $result = $this->service->resolve($p, identity(['email' => 'new@test.com']));

    expect($result->user->role)->toBe(UserRole::ReadWrite);
});

it('blocks SSO entirely when no users exist yet', function () {
    $p = provider();

    expect(fn () => $this->service->resolve($p, identity()))->toThrow(SsoException::class);
});

it('blocks JIT when signup is disabled', function () {
    seedAdmin();
    settings()->set('sso_allow_signup', false);
    $p = provider(['link_by_email' => false]);

    expect(fn () => $this->service->resolve($p, identity(['email' => 'new@test.com'])))
        ->toThrow(SsoException::class);
});

it('reuses an existing identity link and reports 2FA', function () {
    seedAdmin();
    $p = provider();
    $user = User::create(['name' => 'Bob', 'email' => 'bob@test.com', 'password' => 'x', 'role' => UserRole::ReadOnly, 'two_factor_enabled' => true, 'two_factor_confirmed' => true]);
    UserIdentity::create(['user_id' => $user->id, 'identity_provider_id' => $p->id, 'subject' => 'ext-1']);

    $result = $this->service->resolve($p, identity(['email' => 'bob@test.com']));

    expect($result->user->id)->toBe($user->id);
    expect($result->requiresTwoFactor)->toBeTrue();
});

it('never demotes the last admin on role sync', function () {
    $admin = seedAdmin();
    $p = provider([
        'sync_role_on_login' => true,
        'role_mapping' => [['claim' => 'groups', 'operator' => 'contains', 'value' => 'staff', 'role' => 'read-only']],
    ]);
    UserIdentity::create(['user_id' => $admin->id, 'identity_provider_id' => $p->id, 'subject' => 'ext-1']);

    $this->service->resolve($p, identity(['email' => 'root@test.com', 'groups' => ['staff']]));

    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});
