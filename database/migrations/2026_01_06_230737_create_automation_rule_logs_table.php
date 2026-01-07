<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automation_rule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->string('trigger_entity_type', 50)->nullable();
            $table->unsignedBigInteger('trigger_entity_id')->nullable();
            $table->json('actions_executed')->nullable();
            $table->enum('status', ['success', 'error', 'skipped']);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->index(['rule_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_rule_logs');
    }
};
