<?php

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    ssoAdmin();
    ssoGithubProvider();
});

function ghComplete(): \Illuminate\Testing\TestResponse
{
    $state = ssoStartFlow('gh');

    return test()->get("/api/auth/sso/gh/callback?code=abc&state={$state->state}");
}

it('fails when GitHub returns no access token', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['error' => 'bad_verification_code'], 200),
        'api.github.com/*' => Http::response([]),
    ]);

    expect(ssoLocationParam(ghComplete(), 'error'))->not->toBeNull();
});

it('selects the primary verified email even when it is not first', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho']),
        'api.github.com/user' => Http::response(['id' => 7, 'login' => 'oc', 'name' => 'Oc', 'email' => null]),
        'api.github.com/user/emails' => Http::response([
            ['email' => 'secondary@x.com', 'primary' => false, 'verified' => true],
            ['email' => 'unverified@x.com', 'primary' => true, 'verified' => false],
            ['email' => 'primary@x.com', 'primary' => true, 'verified' => true],
        ]),
    ]);

    $res = ghComplete();
    parse_str(parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);
    $this->postJson('/api/auth/sso/exchange', ['ticket' => $q['ticket']])
        ->assertOk()
        ->assertJsonPath('user.email', 'primary@x.com');
});

it('blocks when only a non-primary email is verified', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho']),
        'api.github.com/user' => Http::response(['id' => 8, 'login' => 'np', 'name' => 'Np', 'email' => 'np@x.com']),
        'api.github.com/user/emails' => Http::response([
            ['email' => 'verified-secondary@x.com', 'primary' => false, 'verified' => true],
            ['email' => 'np@x.com', 'primary' => true, 'verified' => false],
        ]),
    ]);

    expect(ssoLocationParam(ghComplete(), 'error'))->not->toBeNull();
    expect(User::where('email', 'np@x.com')->exists())->toBeFalse();
});

it('keys the account on the numeric id, not the renamable login', function () {
    // First login establishes the link for github id 4242.
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho']),
        'api.github.com/user' => Http::response(['id' => 4242, 'login' => 'old-name', 'name' => 'Old', 'email' => null]),
        'api.github.com/user/emails' => Http::response([['email' => 'stable@x.com', 'primary' => true, 'verified' => true]]),
    ]);
    ghComplete();

    expect(User::where('email', 'stable@x.com')->count())->toBe(1);

    // Second login: same id, renamed login + different email — must resolve to the same user.
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho2']),
        'api.github.com/user' => Http::response(['id' => 4242, 'login' => 'new-name', 'name' => 'New', 'email' => null]),
        'api.github.com/user/emails' => Http::response([['email' => 'different@x.com', 'primary' => true, 'verified' => true]]),
    ]);
    ghComplete();

    // No duplicate user, single identity link bound to subject "4242".
    expect(User::whereIn('email', ['stable@x.com', 'different@x.com'])->count())->toBe(1);
    expect(UserIdentity::where('subject', '4242')->count())->toBe(1);
});

it('rejects a forged callback state', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho']),
        'api.github.com/*' => Http::response([]),
    ]);

    expect(ssoLocationParam($this->get('/api/auth/sso/gh/callback?code=abc&state=forged-state'), 'error'))->not->toBeNull();
});
