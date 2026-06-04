<?php

use App\Enums\UserRole;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\TwoFactorChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAuthUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'U',
        'email' => 'u@test.com',
        'password' => 'secret1',
        'role' => UserRole::ReadWrite,
    ], $overrides));
}

function openSession(User $user): array
{
    return app(AuthSessionService::class)->issue($user, request());
}

it('rejects a request once the idle window has passed', function () {
    $issued = openSession(makeAuthUser());
    $issued['session']->forceFill(['idle_expires_at' => now()->subMinute()])->save();

    $this->call('GET', '/api/auth/2fa/status', [], ['svy_session' => $issued['token']])->assertStatus(401);
});

it('rejects a request once the absolute lifetime has passed', function () {
    $issued = openSession(makeAuthUser());
    $issued['session']->forceFill(['absolute_expires_at' => now()->subMinute()])->save();

    $this->call('GET', '/api/auth/2fa/status', [], ['svy_session' => $issued['token']])->assertStatus(401);
});

it('rejects a revoked session', function () {
    $issued = openSession(makeAuthUser());
    app(AuthSessionService::class)->revoke($issued['session']);

    $this->call('GET', '/api/auth/2fa/status', [], ['svy_session' => $issued['token']])->assertStatus(401);
});

it('slides the idle window forward on use', function () {
    $issued = openSession(makeAuthUser());
    $issued['session']->forceFill(['idle_expires_at' => now()->addMinutes(5)])->save();

    $this->call('GET', '/api/auth/2fa/status', [], ['svy_session' => $issued['token']])->assertOk();

    expect($issued['session']->fresh()->idle_expires_at->gt(now()->addMinutes(60)))->toBeTrue();
});

it('rotates the session token after the rotation threshold', function () {
    $issued = openSession(makeAuthUser());
    $issued['session']->forceFill(['created_at' => now()->subHours(2)])->save();
    $oldHash = $issued['session']->token_hash;

    $response = $this->call('GET', '/api/auth/2fa/status', [], ['svy_session' => $issued['token']]);
    $response->assertOk();

    $rotated = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'svy_session');
    expect($rotated)->not->toBeNull();
    expect($issued['session']->fresh()->token_hash)->not->toBe($oldHash);

    // The old token no longer resolves.
    $this->call('GET', '/api/auth/2fa/status', [], ['svy_session' => $issued['token']])->assertStatus(401);
});

it('enforces CSRF on mutating authenticated requests', function () {
    $issued = openSession(makeAuthUser());

    $this->call('POST', '/api/auth/logout', [], ['svy_session' => $issued['token']])->assertStatus(419);

    $this->call('POST', '/api/auth/logout', [], ['svy_session' => $issued['token']], [], ['HTTP_X_CSRF_TOKEN' => $issued['csrf']])
        ->assertOk();
});

it('blocks password login for SSO-only accounts', function () {
    makeAuthUser(['email' => 'sso@test.com', 'is_sso_only' => true]);

    $this->postJson('/api/auth/login', ['email' => 'sso@test.com', 'password' => 'secret1'])->assertStatus(422);
});

it('returns a 2FA challenge instead of a session when 2FA is enabled', function () {
    makeAuthUser(['two_factor_enabled' => true, 'two_factor_confirmed' => true, 'two_factor_secret' => 'SECRET']);

    $response = $this->postJson('/api/auth/login', ['email' => 'u@test.com', 'password' => 'secret1'])
        ->assertOk()
        ->assertJsonPath('requires_2fa', true);

    expect($response->json('two_factor_token'))->not->toBeNull();
    expect(TwoFactorChallenge::count())->toBe(1);
    expect(collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'svy_session'))->toBeNull();
});

it('keeps the 2FA challenge usable after a wrong code (peek, not consume)', function () {
    $svc = app(TwoFactorChallengeService::class);
    $user = makeAuthUser();
    $token = $svc->issue($user);

    expect($svc->resolve($token)->id)->toBe($user->id);
    expect($svc->resolve($token))->not->toBeNull();
});

it('consumes a 2FA challenge exactly once', function () {
    $svc = app(TwoFactorChallengeService::class);
    $token = $svc->issue(makeAuthUser());

    expect($svc->consume($token))->not->toBeNull();
    expect($svc->consume($token))->toBeNull();
});

it('does not resolve an expired 2FA challenge', function () {
    $svc = app(TwoFactorChallengeService::class);
    $token = $svc->issue(makeAuthUser());
    TwoFactorChallenge::query()->update(['expires_at' => now()->subMinute()]);

    expect($svc->resolve($token))->toBeNull();
});
