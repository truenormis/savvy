<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connection(): ?string
    {
        return config('cache.stores.database.connection') ?: config('database.default');
    }

    /**
     * Create the table only when it is missing.
     *
     * These framework tables live on a dedicated connection whose migration
     * bookkeeping is kept in the main database. Restoring an older backup of
     * the main database rewinds that ledger while the shard keeps its tables,
     * so an unguarded create() would abort the whole migration run.
     */
    private function ensure(string $table, callable $definition): void
    {
        if (Schema::connection($this->connection())->hasTable($table)) {
            return;
        }

        Schema::connection($this->connection())->create($table, $definition);
    }

    public function up(): void
    {
        $this->ensure('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        $this->ensure('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('cache');
        Schema::connection($this->connection())->dropIfExists('cache_locks');
    }
};
