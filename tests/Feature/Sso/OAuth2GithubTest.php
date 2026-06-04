<?php

use App\Enums\UserRole;
use App\Models\IdentityProvider;
use App\Models\SsoLoginState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function githubProvider(): IdentityProvider
{
    return IdentityProvider::create([
        'name' => 'GitHub',
        'slug' => 'github',
        'protocol' => 'oauth2',
        'preset' => 'github',
        'enabled' => true,
        'config' => ['client_id' => 'cid'],
        'secrets' => ['client_secret' => 'csecret'],
        'claim_mappings' => ['subject' => 'id', 'email' => 'email', 'name' => 'name', 'groups' => null],
        'allow_jit' => true,
        'link_by_email' => true,
    ]);
}

function startGithub(): string
{
    test()->get('/api/auth/sso/github/redirect')->assertRedirect();

    return SsoLoginState::firstOrFail()->state;
}

beforeEach(function () {
    User::create(['name' => 'Root', 'email' => 'root@test.com', 'password' => 'x', 'role' => UserRole::Admin]);
    githubProvider();
});

it('signs in a GitHub user with a verified primary email', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_x', 'token_type' => 'bearer']),
        'api.github.com/user' => Http::response(['id' => 4242, 'login' => 'octo', 'name' => 'Octo', 'email' => null]),
        'api.github.com/user/emails' => Http::response([
            ['email' => 'octo@test.com', 'primary' => true, 'verified' => true],
        ]),
    ]);

    $state = startGithub();

    $callback = $this->get("/api/auth/sso/github/callback?code=abc&state={$state}");
    $location = $callback->headers->get('Location');
    expect($location)->toContain('ticket=');

    parse_str(parse_url($location, PHP_URL_QUERY), $q);
    $this->postJson('/api/auth/sso/exchange', ['ticket' => $q['ticket']])
        ->assertOk()
        ->assertJsonPath('user.email', 'octo@test.com');

    expect(User::where('email', 'octo@test.com')->where('is_sso_only', true)->exists())->toBeTrue();
});

it('blocks a GitHub user without a verified email', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_x']),
        'api.github.com/user' => Http::response(['id' => 99, 'login' => 'nov', 'name' => 'Nov', 'email' => 'nov@test.com']),
        'api.github.com/user/emails' => Http::response([
            ['email' => 'nov@test.com', 'primary' => true, 'verified' => false],
        ]),
    ]);

    $state = startGithub();

    $callback = $this->get("/api/auth/sso/github/callback?code=abc&state={$state}");
    expect($callback->headers->get('Location'))->toContain('error=');
    expect(User::where('email', 'nov@test.com')->exists())->toBeFalse();
});

it('rejects a replayed state', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_x']),
        'api.github.com/user' => Http::response(['id' => 1, 'name' => 'A', 'email' => 'a@test.com']),
        'api.github.com/user/emails' => Http::response([['email' => 'a@test.com', 'primary' => true, 'verified' => true]]),
    ]);

    $state = startGithub();
    $this->get("/api/auth/sso/github/callback?code=abc&state={$state}");

    // Second use of the same state must fail.
    $this->get("/api/auth/sso/github/callback?code=abc&state={$state}")
        ->assertRedirect();
    expect($this->get("/api/auth/sso/github/callback?code=abc&state={$state}")->headers->get('Location'))
        ->toContain('error=');
});
