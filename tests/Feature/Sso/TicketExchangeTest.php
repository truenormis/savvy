<?php

use App\DTOs\ProvisionResult;
use App\Enums\UserRole;
use App\Models\SsoLoginTicket;
use App\Models\User;
use App\Services\Sso\SsoTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeUser(bool $twoFactor = false): User
{
    return User::create([
        'name' => 'U',
        'email' => 'u@test.com',
        'password' => 'x',
        'role' => UserRole::ReadWrite,
        'two_factor_enabled' => $twoFactor,
        'two_factor_confirmed' => $twoFactor,
    ]);
}

it('exchanges a ticket for a session exactly once', function () {
    $user = makeUser();
    $ticket = app(SsoTicketService::class)->issue(new ProvisionResult($user, false));

    $response = $this->postJson('/api/auth/sso/exchange', ['ticket' => $ticket])
        ->assertOk()
        ->assertJsonPath('user.email', 'u@test.com');

    // The session is established via the httpOnly cookie, not a JSON token.
    expect($response->json())->not->toHaveKey('token');
    $session = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'svy_session');
    expect($session)->not->toBeNull();
    expect($session->isHttpOnly())->toBeTrue();

    // Single-use: a replay must fail.
    $this->postJson('/api/auth/sso/exchange', ['ticket' => $ticket])->assertStatus(410);
});

it('returns the 2FA challenge instead of a token when 2FA is enabled', function () {
    $user = makeUser(twoFactor: true);
    $ticket = app(SsoTicketService::class)->issue(new ProvisionResult($user, true));

    $this->postJson('/api/auth/sso/exchange', ['ticket' => $ticket])
        ->assertOk()
        ->assertJsonPath('requires_2fa', true)
        ->assertJsonStructure(['requires_2fa', 'two_factor_token']);
});

it('rejects an expired ticket', function () {
    $user = makeUser();
    $ticket = app(SsoTicketService::class)->issue(new ProvisionResult($user, false));
    SsoLoginTicket::where('ticket', $ticket)->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/api/auth/sso/exchange', ['ticket' => $ticket])->assertStatus(410);
});

it('rejects an unknown ticket', function () {
    $this->postJson('/api/auth/sso/exchange', ['ticket' => 'nope'])->assertStatus(410);
});
