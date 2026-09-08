<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connection(): ?string
    {
        return config('session.connection') ?: config('database.default');
    }

    /**
     * Create the table only when it is missing.
     *
     * This framework table lives on a dedicated connection whose migration
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
        $this->ensure('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('sessions');
    }
};
