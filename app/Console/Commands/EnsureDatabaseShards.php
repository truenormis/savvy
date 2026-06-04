<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnsureDatabaseShards extends Command
{
    protected $signature = 'app:ensure-shards';

    protected $description = 'Create queue/cache/session framework tables on their dedicated connections if missing';

    public function handle(): int
    {
        $queue = config('queue.connections.database.connection') ?: config('database.default');
        $cache = config('cache.stores.database.connection') ?: config('database.default');
        $session = config('session.connection') ?: config('database.default');
        $batches = config('queue.batching.database') ?: config('database.default');
        $failed = config('queue.failed.database') ?: config('database.default');

        $this->ensure($queue, 'jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $this->ensure($failed, 'failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        $this->ensure($batches, 'job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        $this->ensure($cache, 'cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        $this->ensure($cache, 'cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        $this->ensure($session, 'sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        return self::SUCCESS;
    }

    private function ensure(?string $connection, string $table, callable $schema): void
    {
        if (Schema::connection($connection)->hasTable($table)) {
            return;
        }

        Schema::connection($connection)->create($table, $schema);
        $this->info("Created {$table} on connection [".($connection ?? 'default').'].');
    }
}
