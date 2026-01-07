<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expense', 'transfer']);
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 16, 2);
            $table->decimal('to_amount', 16, 2)->nullable();
            $table->string('description')->nullable();

            // Schedule
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->unsignedTinyInteger('interval')->default(1);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();

            // Control
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_date')->index();
            $table->date('last_run_date')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('recurring_transaction_tag', function (Blueprint $table) {
            $table->foreignId('recurring_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['recurring_transaction_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transaction_tag');
        Schema::dropIfExists('recurring_transactions');
    }
};
