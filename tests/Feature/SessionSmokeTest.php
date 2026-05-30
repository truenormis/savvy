<?php

use App\Models\AuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('register sets cookies, me works, logout revokes', function () {
    $reg = $this->postJson('/api/auth/register', ['name' => 'A', 'email' => 'a@a.com', 'password' => 'secret1']);
    $reg->assertCreated()->assertJsonPath('user.email', 'a@a.com');

    $cookies = collect($reg->headers->getCookies());
    $session = $cookies->first(fn ($c) => $c->getName() === 'svy_session');
    $csrf = $cookies->first(fn ($c) => $c->getName() === 'svy_csrf');

    expect($session)->not->toBeNull();
    expect($session->isHttpOnly())->toBeTrue();
    expect($csrf)->not->toBeNull();
    expect($csrf->isHttpOnly())->toBeFalse();
    expect(AuthSession::count())->toBe(1);

    $jar = ['svy_session' => $session->getValue()];
    $csrfHeader = ['HTTP_X_CSRF_TOKEN' => $csrf->getValue()];

    $this->call('GET', '/api/auth/me', [], $jar)->assertOk();
    $this->call('POST', '/api/auth/logout', [], $jar)->assertStatus(419);
    $this->call('POST', '/api/auth/logout', [], $jar, [], $csrfHeader)->assertOk();

    expect(AuthSession::first()->revoked_at)->not->toBeNull();
    // /auth/me is a public probe (always 200); a genuinely protected route 401s.
    $this->call('GET', '/api/auth/2fa/status', [], $jar)->assertStatus(401);
});

it('uses the __Host- prefixed cookie over HTTPS', function () {
    $reg = $this->postJson('https://savvy.example/api/auth/register', ['name' => 'A', 'email' => 'a@a.com', 'password' => 'secret1']);
    $reg->assertCreated();

    $cookies = collect($reg->headers->getCookies());
    $session = $cookies->first(fn ($c) => $c->getName() === '__Host-svy_session');

    expect($session)->not->toBeNull();
    expect($session->isSecure())->toBeTrue();
    expect($session->getPath())->toBe('/');
    expect($session->getDomain())->toBeNull();

    // Cookie still resolves through the middleware over HTTPS.
    $this->call('GET', 'https://savvy.example/api/auth/me', [], ['__Host-svy_session' => $session->getValue()])
        ->assertOk()->assertJsonPath('user.email', 'a@a.com');
});
