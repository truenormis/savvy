<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connection(): ?string
    {
        return config('queue.failed.database') ?: config('database.default');
    }

    /**
     * Create the table only when it is missing. See the note in the sessions
     * migration: the shard keeps its tables when a restored main database
     * rewinds the migration ledger.
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
        $this->ensure('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('failed_jobs');
    }
};
