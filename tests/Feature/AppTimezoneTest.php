<?php

use App\DTOs\ReportFilterData;
use App\Models\Account;
use App\Models\Currency;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\RecurringTransactionService;
use App\Services\SettingsService;
use App\Support\AppTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->forgetInstance(SettingsService::class);
});

it('defaults to the app timezone when nothing is configured', function () {
    expect(AppTime::timezone())->toBe(config('app.timezone'));
});

it('reads the configured timezone', function () {
    settings()->set('timezone', 'America/New_York');
    app()->forgetInstance(SettingsService::class);

    expect(AppTime::timezone())->toBe('America/New_York');
});

it('falls back to the app timezone when the stored value is not a real zone', function () {
    settings()->set('timezone', 'Mars/Olympus_Mons');
    app()->forgetInstance(SettingsService::class);

    expect(AppTime::timezone())->toBe(config('app.timezone'));
});

it('falls back to the app timezone when settings are unreachable', function () {
    app()->bind(SettingsService::class, function () {
        throw new RuntimeException('no such table: settings');
    });

    expect(AppTime::timezone())->toBe(config('app.timezone'))
        ->and(AppTime::today())->toBe(now()->toDateString());

    app()->forgetInstance(SettingsService::class);
});

it('resolves today in the configured timezone, not utc', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-09 02:30:00', 'UTC'));

    settings()->set('timezone', 'America/New_York');
    app()->forgetInstance(SettingsService::class);

    expect(AppTime::today())->toBe('2026-09-08')
        ->and(now()->toDateString())->toBe('2026-09-09');

    Carbon::setTestNow();
});

it('does not run a recurring transaction before its local day starts', function () {
    $currency = Currency::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'rate' => 1, 'decimals' => 2]
    );

    $account = Account::create([
        'name' => 'Main',
        'type' => 'bank',
        'currency_id' => $currency->id,
        'initial_balance' => 0,
        'is_active' => true,
    ]);

    $recurring = RecurringTransaction::create([
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => 25,
        'description' => 'Rent',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => '2026-01-09',
        'next_run_date' => '2026-09-09',
        'is_active' => true,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-09 02:30:00', 'UTC'));

    settings()->set('timezone', 'America/New_York');
    app()->forgetInstance(SettingsService::class);

    expect(RecurringTransaction::due()->pluck('id'))->not->toContain($recurring->id);

    Carbon::setTestNow(Carbon::parse('2026-09-09 14:00:00', 'UTC'));

    expect(RecurringTransaction::due()->pluck('id'))->toContain($recurring->id);

    Carbon::setTestNow();
});

it('anchors the default report month to the configured timezone', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 01:00:00', 'UTC'));

    settings()->set('timezone', 'America/Los_Angeles');
    app()->forgetInstance(SettingsService::class);

    $range = (new ReportFilterData(periodType: 'month'))->getDateRange();

    expect($range['start']->toDateString())->toBe('2026-09-01')
        ->and($range['end']->toDateString())->toBe('2026-09-30');

    Carbon::setTestNow();
});

it('dates a generated recurring transaction by the local day, not the utc one', function () {
    $currency = Currency::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'rate' => 1, 'decimals' => 2]
    );

    $account = Account::create([
        'name' => 'Main',
        'type' => 'bank',
        'currency_id' => $currency->id,
        'initial_balance' => 0,
        'is_active' => true,
    ]);

    $recurring = RecurringTransaction::create([
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => 25,
        'description' => 'Rent',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => '2026-01-01',
        'next_run_date' => '2026-09-01',
        'is_active' => true,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-02 03:00:00', 'UTC'));

    settings()->set('timezone', 'America/New_York');
    app()->forgetInstance(SettingsService::class);

    $generated = app(RecurringTransactionService::class)->processDue();

    expect($generated)->toHaveCount(1)
        ->and($generated[0]->date->toDateString())->toBe('2026-09-01')
        ->and(now()->toDateString())->toBe('2026-09-02')
        ->and($recurring->fresh()->last_run_date->toDateString())->toBe('2026-09-01');

    Carbon::setTestNow();
});

it('does not post a recurring transaction twice when the cron runs again the same day', function () {
    $currency = Currency::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'rate' => 1, 'decimals' => 2]
    );

    $account = Account::create([
        'name' => 'Main',
        'type' => 'bank',
        'currency_id' => $currency->id,
        'initial_balance' => 0,
        'is_active' => true,
    ]);

    RecurringTransaction::create([
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => 25,
        'description' => 'Rent',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => '2026-01-01',
        'next_run_date' => '2026-09-09',
        'is_active' => true,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-09 09:00:00', 'UTC'));

    $service = app(RecurringTransactionService::class);

    expect($service->processDue())->toHaveCount(1);

    Carbon::setTestNow(Carbon::parse('2026-09-09 09:01:00', 'UTC'));
    expect($service->processDue())->toHaveCount(0);

    Carbon::setTestNow(Carbon::parse('2026-09-09 23:59:00', 'UTC'));
    expect($service->processDue())->toHaveCount(0);

    expect(Transaction::where('description', 'Rent')->count())->toBe(1);

    Carbon::setTestNow();
});

it('serves a timezone list that excludes the tzdata pseudo entries', function () {
    $names = array_column(AppTime::available(), 'name');

    expect($names)->toContain('UTC', 'Europe/Kyiv', 'Europe/Kiev', 'America/New_York')
        ->and($names)->not->toContain('leapseconds')
        ->and($names)->not->toContain('tzdata.zi');

    foreach (AppTime::available() as $zone) {
        expect(fn () => Carbon::now($zone['name']))->not->toThrow(Throwable::class);
    }
})->skip(fn () => count(AppTime::available()) > 700, 'guards against a pathological tzdata list');

it('offers exactly the timezones the settings endpoint accepts', function () {
    $admin = ssoAdmin();
    $served = array_column(AppTime::available(), 'name');

    expect($served)->toBe(AppTime::identifiers());

    callAs('PATCH', '/api/settings', ['timezone' => 'leapseconds'], $admin)
        ->assertStatus(422);

    callAs('PATCH', '/api/settings', ['timezone' => 'Asia/Calcutta'], $admin)
        ->assertOk();
});

it('exposes the list over the api with offsets', function () {
    $admin = ssoAdmin();

    $response = callAs('GET', '/api/timezones', [], $admin)->assertOk();

    $payload = $response->json();
    $byName = array_column($payload['timezones'], null, 'name');

    expect($payload['current'])->toBe(AppTime::timezone())
        ->and($byName['UTC']['offset'])->toBe('+00:00')
        ->and($byName['UTC']['canonical'])->toBeTrue()
        ->and($byName['Europe/Kiev']['canonical'])->toBeFalse()
        ->and($byName['Europe/Kyiv']['canonical'])->toBeTrue();
});

it('accepts the backward-compatible zone names browsers still report', function () {
    $admin = ssoAdmin();

    expect(timezone_identifiers_list())->not->toContain('Europe/Kiev');

    callAs('PATCH', '/api/settings', ['timezone' => 'Europe/Kiev'], $admin)
        ->assertOk();

    app()->forgetInstance(SettingsService::class);

    expect(AppTime::timezone())->toBe('Europe/Kiev')
        ->and(AppTime::now()->getOffset())->toBe(AppTime::now()->setTimezone('Europe/Kyiv')->getOffset());
});

it('rejects an invalid timezone through the settings endpoint', function () {
    $admin = ssoAdmin();

    callAs('PATCH', '/api/settings', ['timezone' => 'Nowhere/Land'], $admin)
        ->assertStatus(422);

    callAs('PATCH', '/api/settings', ['timezone' => 'Europe/Kyiv'], $admin)
        ->assertOk();

    app()->forgetInstance(SettingsService::class);

    expect(AppTime::timezone())->toBe('Europe/Kyiv');
});
