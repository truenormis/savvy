<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connection(): ?string
    {
        return config('queue.connections.database.connection') ?: config('database.default');
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
        $this->ensure('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('jobs');
    }
};
