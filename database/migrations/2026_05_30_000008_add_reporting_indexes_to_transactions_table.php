<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Covering index for per-account balance/period sums:
            // WHERE account_id = ? AND type IN (...) [AND date < ?] -> SUM(amount)
            $table->index(['account_id', 'type', 'date', 'amount'], 'transactions_account_type_date_amount_index');

            // Covering index for transfer-in sums: WHERE to_account_id = ? [AND date < ?] -> SUM(to_amount)
            $table->index(['to_account_id', 'date', 'to_amount'], 'transactions_to_account_date_amount_index');

            // Covering index for global report/category sums: WHERE type = ? AND date BETWEEN ? AND ? -> SUM(amount)
            $table->index(['type', 'date', 'amount'], 'transactions_type_date_amount_index');

            // Per-account chronological listing / date-range filtering
            $table->index(['account_id', 'date'], 'transactions_account_date_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Superseded by the composite indexes above
            $table->dropIndex('transactions_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_account_type_date_amount_index');
            $table->dropIndex('transactions_to_account_date_amount_index');
            $table->dropIndex('transactions_type_date_amount_index');
            $table->dropIndex('transactions_account_date_index');
            $table->index('type', 'transactions_type_index');
        });
    }
};
