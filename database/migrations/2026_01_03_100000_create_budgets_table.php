<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->enum('period', ['weekly', 'monthly', 'yearly', 'one_time']);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_global')->default(false);
            $table->unsignedTinyInteger('notify_at_percent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('budget_category', function (Blueprint $table) {
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['budget_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_category');
        Schema::dropIfExists('budgets');
    }
};
