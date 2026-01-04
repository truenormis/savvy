<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expense', 'transfer']);
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('to_account_id')->nullable()->constrained('accounts');
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->decimal('amount', 16, 2);
            $table->decimal('to_amount', 16, 2)->nullable();
            $table->decimal('exchange_rate', 16, 6)->nullable();
            $table->string('description')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->index('date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
