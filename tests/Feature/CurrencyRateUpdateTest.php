<?php

use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function rateUser(): User
{
    return User::create([
        'name' => 'Rates',
        'email' => 'rates-'.uniqid().'@example.com',
        'password' => 'secret1',
        'role' => UserRole::ReadWrite,
    ]);
}

function fakeUsdRates(array $rates = ['eur' => 0.85, 'uah' => 40]): void
{
    Http::fake([
        'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json' => Http::response([
            'date' => '2026-08-21',
            'usd' => $rates,
        ], 200),
    ]);
}

it('does not refresh all rates when a currency is created', function () {
    Currency::create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'is_base' => true,
        'rate' => 1,
    ]);
    Http::fake();

    $response = callAs('POST', '/api/currencies', [
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'decimals' => 2,
        'rate' => 1.5,
    ], rateUser());

    $response->assertCreated()->assertJsonPath('data.rate', 1.5);
    Http::assertNothingSent();
});

it('returns catalog suggestions with rates and skips existing currencies', function () {
    Currency::create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'is_base' => true,
        'rate' => 1,
    ]);

    Http::fake([
        'https://cdn.jsdelivr.net/gh/fawazahmed0/exchange-api@main/other/Common-Currency.json' => Http::response([
            'USD' => ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_digits' => 2],
            'EUR' => ['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR', 'symbol_native' => '€', 'decimal_digits' => 2],
        ], 200),
        'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json' => Http::response([
            'date' => '2026-08-21',
            'usd' => ['eur' => 0.85],
        ], 200),
    ]);

    $response = callAs('GET', '/api/currencies/catalog', [], rateUser());

    $response->assertOk();
    $codes = collect($response->json('data'))->pluck('code');
    expect($codes)->not->toContain('USD')->toContain('EUR');

    $eur = collect($response->json('data'))->firstWhere('code', 'EUR');
    expect($eur['name'])->toBe('Euro')
        ->and($eur['symbol'])->toBe('€')
        ->and($eur['decimals'])->toBe(2)
        ->and($eur['rate'])->toBeGreaterThan(1.17)
        ->and($eur['rate'])->toBeLessThan(1.18);
});

it('returns an empty catalog when the CDN is unavailable', function () {
    Http::fake([
        'https://cdn.jsdelivr.net/gh/fawazahmed0/exchange-api@main/other/Common-Currency.json' => Http::response('blocked', 403),
    ]);

    callAs('GET', '/api/currencies/catalog', [], rateUser())
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('refreshes rates when auto-update is turned on', function () {
    settings()->set('auto_update_currencies', false);
    Currency::create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'is_base' => true,
        'rate' => 1,
    ]);
    $eur = Currency::create([
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'is_base' => false,
        'rate' => 1.5,
    ]);
    fakeUsdRates(['eur' => 0.85]);

    callAs('PATCH', '/api/settings', [
        'auto_update_currencies' => true,
    ], rateUser())->assertOk();

    expect((float) $eur->fresh()->rate)->toBeGreaterThan(1.17)
        ->and((float) $eur->fresh()->rate)->toBeLessThan(1.18);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/currencies/usd.json'));
});

it('does not call the API when auto-update is turned off', function () {
    Http::fake();
    settings()->set('auto_update_currencies', true);

    callAs('PATCH', '/api/settings', [
        'auto_update_currencies' => false,
    ], rateUser())->assertOk();

    Http::assertNothingSent();
});
