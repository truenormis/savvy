<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_login_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('requires_2fa')->default(false);
            $table->text('two_factor_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_login_tickets');
    }
};
