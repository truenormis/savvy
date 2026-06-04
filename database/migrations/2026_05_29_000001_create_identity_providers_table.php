<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('protocol');
            $table->string('preset');
            $table->boolean('enabled')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('config')->nullable();
            $table->text('secrets')->nullable();
            $table->json('claim_mappings')->nullable();
            $table->json('role_mapping')->nullable();
            $table->string('default_role')->default('read-only');
            $table->boolean('allow_jit')->default(true);
            $table->boolean('sync_role_on_login')->default(false);
            $table->boolean('link_by_email')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_providers');
    }
};
