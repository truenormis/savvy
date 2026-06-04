<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->char('dedup_hash', 32)->nullable()->after('description');
            $table->unique(['account_id', 'dedup_hash'], 'transactions_account_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_account_dedup_unique');
            $table->dropColumn('dedup_hash');
        });
    }
};
