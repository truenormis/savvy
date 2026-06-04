<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_login_states', function (Blueprint $table) {
            $table->id();
            $table->string('state')->unique();
            $table->foreignId('identity_provider_id')->constrained()->cascadeOnDelete();
            $table->string('nonce')->nullable();
            $table->text('code_verifier')->nullable();
            $table->string('saml_request_id')->nullable();
            $table->string('redirect_after')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
            $table->index('saml_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_login_states');
    }
};
