<?php

use App\DTOs\NormalizedIdentity;
use App\Enums\UserRole;
use App\Exceptions\SsoException;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Sso\ProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function id2(array $o = []): NormalizedIdentity
{
    return NormalizedIdentity::fromArray(array_merge([
        'subject' => 'sub-x',
        'email' => 'person@example.com',
        'email_verified' => true,
        'name' => 'Person',
        'groups' => [],
        'raw' => [],
    ], $o));
}

beforeEach(function () {
    $this->svc = app(ProvisioningService::class);
    ssoAdmin();
});

it('links case-insensitively to an existing email', function () {
    User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'x', 'role' => UserRole::ReadWrite]);
    $p = ssoOidcProvider();

    $result = $this->svc->resolve($p, id2(['email' => 'JANE@Example.COM']));

    expect(strtolower($result->user->email))->toBe('jane@example.com');
    // No duplicate account was created.
    expect(User::whereRaw('lower(email) = ?', ['jane@example.com'])->count())->toBe(1);
});

it('matches the "equals" operator against a scalar raw claim', function () {
    $p = ssoOidcProvider(['role_mapping' => [['claim' => 'department', 'operator' => 'equals', 'value' => 'finance', 'role' => 'read-write']]]);

    $result = $this->svc->resolve($p, id2(['email' => 'eq@example.com', 'raw' => ['department' => 'finance']]));

    expect($result->user->role)->toBe(UserRole::ReadWrite);
});

it('matches the "one_of" operator against an array claim', function () {
    $p = ssoOidcProvider(['role_mapping' => [['claim' => 'groups', 'operator' => 'one_of', 'value' => 'admins', 'role' => 'admin']]]);

    $result = $this->svc->resolve($p, id2(['email' => 'oo@example.com', 'groups' => ['staff', 'admins']]));

    expect($result->user->role)->toBe(UserRole::Admin);
});

it('matches a nested dot-path claim with "contains"', function () {
    $p = ssoOidcProvider(['role_mapping' => [['claim' => 'realm_access.roles', 'operator' => 'contains', 'value' => 'app-admin', 'role' => 'admin']]]);

    $result = $this->svc->resolve($p, id2(['email' => 'dp@example.com', 'raw' => ['realm_access' => ['roles' => ['user', 'app-admin']]]]));

    expect($result->user->role)->toBe(UserRole::Admin);
});

it('applies the first matching rule and stops', function () {
    $p = ssoOidcProvider(['role_mapping' => [
        ['claim' => 'groups', 'operator' => 'contains', 'value' => 'rw', 'role' => 'read-write'],
        ['claim' => 'groups', 'operator' => 'contains', 'value' => 'rw', 'role' => 'admin'],
    ]]);

    $result = $this->svc->resolve($p, id2(['email' => 'fm@example.com', 'groups' => ['rw']]));

    expect($result->user->role)->toBe(UserRole::ReadWrite);
});

it('keeps the same subject independent across two providers', function () {
    $a = ssoOidcProvider(['slug' => 'a', 'name' => 'A']);
    $b = ssoOidcProvider(['slug' => 'b', 'name' => 'B']);

    $ra = $this->svc->resolve($a, id2(['subject' => 'shared-123', 'email' => 'a-user@example.com']));
    $rb = $this->svc->resolve($b, id2(['subject' => 'shared-123', 'email' => 'b-user@example.com']));

    expect($ra->user->id)->not->toBe($rb->user->id);
    expect(UserIdentity::where('subject', 'shared-123')->count())->toBe(2);
});

it('refuses JIT when linking is off and the email already exists', function () {
    User::create(['name' => 'Taken', 'email' => 'taken@example.com', 'password' => 'x', 'role' => UserRole::ReadOnly]);
    $p = ssoOidcProvider(['link_by_email' => false]);

    expect(fn () => $this->svc->resolve($p, id2(['email' => 'taken@example.com'])))
        ->toThrow(SsoException::class);
});

it('provisions an account regardless of the IdP email_verified claim', function () {
    $p = ssoOidcProvider();

    $result = $this->svc->resolve($p, id2(['email' => 'unv@example.com', 'email_verified' => false]));

    expect($result->user->email)->toBe('unv@example.com');
});

it('upgrades an existing user role on sync', function () {
    $user = User::create(['name' => 'Low', 'email' => 'low@example.com', 'password' => 'x', 'role' => UserRole::ReadOnly]);
    $p = ssoOidcProvider(['sync_role_on_login' => true, 'role_mapping' => [['claim' => 'groups', 'operator' => 'contains', 'value' => 'power', 'role' => 'read-write']]]);
    UserIdentity::create(['user_id' => $user->id, 'identity_provider_id' => $p->id, 'subject' => 'sub-x']);

    $this->svc->resolve($p, id2(['email' => 'low@example.com', 'groups' => ['power']]));

    expect($user->fresh()->role)->toBe(UserRole::ReadWrite);
});

it('allows demoting an admin on sync when another admin remains', function () {
    User::create(['name' => 'Admin2', 'email' => 'admin2@example.com', 'password' => 'x', 'role' => UserRole::Admin]);
    $demotable = User::create(['name' => 'Admin3', 'email' => 'admin3@example.com', 'password' => 'x', 'role' => UserRole::Admin]);
    $p = ssoOidcProvider(['sync_role_on_login' => true, 'role_mapping' => [['claim' => 'groups', 'operator' => 'contains', 'value' => 'staff', 'role' => 'read-only']]]);
    UserIdentity::create(['user_id' => $demotable->id, 'identity_provider_id' => $p->id, 'subject' => 'sub-x']);

    $this->svc->resolve($p, id2(['email' => 'admin3@example.com', 'groups' => ['staff']]));

    expect($demotable->fresh()->role)->toBe(UserRole::ReadOnly);
});

it('records last_login and claims on re-login', function () {
    $user = User::create(['name' => 'Ret', 'email' => 'ret@example.com', 'password' => 'x', 'role' => UserRole::ReadOnly]);
    $p = ssoOidcProvider();
    UserIdentity::create(['user_id' => $user->id, 'identity_provider_id' => $p->id, 'subject' => 'sub-x']);

    $this->svc->resolve($p, id2(['email' => 'ret@example.com', 'raw' => ['hd' => 'example.com']]));

    $link = UserIdentity::where('identity_provider_id', $p->id)->where('subject', 'sub-x')->first();
    expect($link->last_login_at)->not->toBeNull();
    expect($link->claims)->toBe(['hd' => 'example.com']);
});
