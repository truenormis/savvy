<?php

use App\Builders\TransactionQueryBuilder;
use App\DTOs\TransactionFilterData;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Services\CategoryService;
use App\Services\Import\DuplicateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function boundaryCurrency(): Currency
{
    return Currency::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'rate' => 1, 'decimals' => 2]
    );
}

function boundaryAccount(): Account
{
    return Account::create([
        'name' => 'Main',
        'type' => 'bank',
        'currency_id' => boundaryCurrency()->id,
        'initial_balance' => 0,
        'is_active' => true,
    ]);
}

function insertOn(Account $account, string $storedDate, float $amount = 10, ?int $categoryId = null): int
{
    return DB::table('transactions')->insertGetId([
        'type' => 'expense',
        'account_id' => $account->id,
        'category_id' => $categoryId,
        'amount' => $amount,
        'description' => 'boundary probe',
        'date' => $storedDate,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function filterCount(array $filters): int
{
    return TransactionQueryBuilder::make()
        ->applyFilters(TransactionFilterData::fromArray($filters))
        ->getQuery()
        ->count();
}

describe('transaction list date filter', function () {
    it('includes transactions stored at midnight on the end date', function () {
        $account = boundaryAccount();
        insertOn($account, '2026-09-08 00:00:00');

        expect(filterCount(['start_date' => '2026-09-07', 'end_date' => '2026-09-08']))->toBe(1);
    });

    it('includes transactions stored with a time component on the end date', function () {
        $account = boundaryAccount();
        insertOn($account, '2026-09-08 19:01:00');

        expect(filterCount(['start_date' => '2026-09-07', 'end_date' => '2026-09-08']))->toBe(1);
    });

    it('matches a single day selected as both bounds', function () {
        $account = boundaryAccount();
        insertOn($account, '2026-09-08 00:00:00');
        insertOn($account, '2026-09-08 23:59:59');
        insertOn($account, '2026-09-09 00:00:00');

        expect(filterCount(['start_date' => '2026-09-08', 'end_date' => '2026-09-08']))->toBe(2);
    });

    it('still excludes the day after the end date', function () {
        $account = boundaryAccount();
        insertOn($account, '2026-09-09 00:00:00');

        expect(filterCount(['start_date' => '2026-09-07', 'end_date' => '2026-09-08']))->toBe(0);
    });

    it('includes transactions stored with a time component on the start date', function () {
        $account = boundaryAccount();
        insertOn($account, '2026-09-07 08:30:00');

        expect(filterCount(['start_date' => '2026-09-07', 'end_date' => '2026-09-08']))->toBe(1);
    });
});

describe('category statistics date filter', function () {
    it('counts transactions dated on the end date', function () {
        $account = boundaryAccount();
        $category = Category::create(['name' => 'Food', 'type' => 'expense']);

        insertOn($account, '2026-09-08 12:00:00', 25, $category->id);

        $stats = app(CategoryService::class)->getStatistics($category->id, '2026-09-08', '2026-09-08');

        expect($stats['transactions_count'])->toBe(1)
            ->and($stats['total_amount'])->toBe(25.0);
    });
});

describe('import duplicate detection', function () {
    it('sees an existing transaction dated on the end of the loaded window', function () {
        $account = boundaryAccount();
        insertOn($account, '2026-09-08 00:00:00', 12.5);

        $checker = new DuplicateChecker;
        $checker->loadExistingTransactions($account->id, '2026-09-08', '2026-09-08');

        expect($checker->isDuplicate('2026-09-08', 12.5, 'boundary probe'))->not->toBeNull();
    });
});
