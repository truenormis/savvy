<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connection(): ?string
    {
        return config('queue.batching.database') ?: config('database.default');
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
        $this->ensure('job_batches', function (Blueprint $table) {
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
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('job_batches');
    }
};
