<?php

use App\Models\Account;
use App\Models\AutomationRule;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Import\CsvImportService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function ledgerAccount(): Account
{
    $currency = Currency::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'exchange_rate' => 1]
    );

    return Account::create([
        'name' => 'Main', 'type' => 'bank', 'currency_id' => $currency->id,
        'initial_balance' => 0, 'is_active' => true,
    ]);
}

function rawTransaction(Account $account, string $date, float $amount, ?string $description): int
{
    return DB::table('transactions')->insertGetId([
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => $amount,
        'description' => $description,
        'date' => $date.' 00:00:00',
        'dedup_hash' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function runBackfill(): void
{
    $migration = require database_path('migrations/2026_06_10_000001_backfill_transaction_dedup_hash.php');
    $migration->up();
}

describe('seeding never destroys a real ledger', function () {
    it('seeds reference data only, never demo accounts or logins', function () {
        $this->seed(DatabaseSeeder::class);

        expect(Currency::count())->toBeGreaterThan(0)
            ->and(User::count())->toBe(0)
            ->and(Account::count())->toBe(0)
            ->and(Transaction::count())->toBe(0);
    });

    it('refuses to seed demo data over an existing ledger', function () {
        $account = ledgerAccount();
        rawTransaction($account, '2026-03-10', 4.50, 'Coffee');
        User::create(['name' => 'Real', 'email' => 'real@example.com', 'password' => 'secret1', 'role' => 'admin']);

        $this->seed(DemoSeeder::class);

        expect(Transaction::count())->toBe(1)
            ->and(Account::count())->toBe(1)
            ->and(User::where('email', 'real@example.com')->exists())->toBeTrue()
            ->and(User::where('email', 'admin@savvy.app')->exists())->toBeFalse()
            ->and(AutomationRule::count())->toBe(0);
    });
});

describe('dedup hash backfill', function () {
    it('gives pre-1.3 rows the hash the importer computes, so a re-import is skipped', function () {
        $account = ledgerAccount();
        $id = rawTransaction($account, '2026-03-10', 4.50, '  Coffee  ');

        runBackfill();

        $stored = DB::table('transactions')->where('id', $id)->value('dedup_hash');

        $service = app(CsvImportService::class);
        $method = new ReflectionMethod($service, 'dedupHash');
        $method->setAccessible(true);

        expect($stored)->not->toBeNull()
            ->and($stored)->toBe($method->invoke($service, '2026-03-10', 4.50, '  Coffee  '));
    });

    it('leaves genuine same-account duplicates null so the unique index cannot fail', function () {
        $account = ledgerAccount();
        $first = rawTransaction($account, '2026-03-10', 4.50, 'Coffee');
        $second = rawTransaction($account, '2026-03-10', 4.50, 'Coffee');

        runBackfill();

        expect(DB::table('transactions')->where('id', $first)->value('dedup_hash'))->not->toBeNull()
            ->and(DB::table('transactions')->where('id', $second)->value('dedup_hash'))->toBeNull()
            ->and(Transaction::count())->toBe(2);
    });

    it('hashes the same row on two accounts, because the index is per account', function () {
        $one = ledgerAccount();
        $two = ledgerAccount();
        $a = rawTransaction($one, '2026-03-10', 4.50, 'Coffee');
        $b = rawTransaction($two, '2026-03-10', 4.50, 'Coffee');

        runBackfill();

        $hashA = DB::table('transactions')->where('id', $a)->value('dedup_hash');
        $hashB = DB::table('transactions')->where('id', $b)->value('dedup_hash');

        expect($hashA)->not->toBeNull()->and($hashB)->toBe($hashA);
    });

    it('is safe to run twice', function () {
        $account = ledgerAccount();
        rawTransaction($account, '2026-03-10', 4.50, 'Coffee');

        runBackfill();
        runBackfill();

        expect(Transaction::count())->toBe(1);
    });
});

describe('framework tables on shard connections', function () {
    it('skips tables that already exist, so a restored ledger cannot abort the upgrade', function () {
        $migrations = [
            '2026_05_28_202130_create_jobs_table.php',
            '2026_05_28_202130_create_failed_jobs_table.php',
            '2026_05_30_000003_create_cache_table.php',
            '2026_05_30_000004_create_sessions_table.php',
            '2026_05_30_000009_create_job_batches_table.php',
        ];

        foreach ($migrations as $file) {
            $migration = require database_path('migrations/'.$file);

            // Re-running up() is exactly what happens when a restored main
            // database rewinds the ledger while the shard keeps its tables.
            $migration->up();
        }

        expect(Schema::hasTable('jobs'))->toBeTrue()
            ->and(Schema::hasTable('cache'))->toBeTrue()
            ->and(Schema::hasTable('sessions'))->toBeTrue();
    });
});

describe('blank shard configuration', function () {
    it('falls back to the main database instead of an empty path', function () {
        expect(config('database.connections.sqlite_queue.database'))->not->toBe('')
            ->and(config('database.connections.sqlite_cache.database'))->not->toBe('')
            ->and(config('database.connections.sqlite_sessions.database'))->not->toBe('')
            ->and(config('filesystems.disks.uploads.root'))->not->toBe('');
    });
});
