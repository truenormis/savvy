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

    public function up(): void
    {
        Schema::connection($this->connection())->create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::connection($this->connection())->create('cache_locks', function (Blueprint $table) {
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
