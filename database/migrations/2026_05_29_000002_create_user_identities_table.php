<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('identity_provider_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->timestamp('last_login_at')->nullable();
            $table->text('claims')->nullable();
            $table->timestamps();

            $table->unique(['identity_provider_id', 'subject']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identities');
    }
};
