<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_sessions', function (Blueprint $table) {
            $table->timestamp('rotated_at')->nullable()->after('csrf');
            $table->string('previous_token_hash', 64)->nullable()->after('rotated_at');
            $table->string('previous_csrf', 64)->nullable()->after('previous_token_hash');
            $table->timestamp('previous_expires_at')->nullable()->after('previous_csrf');
        });

        Schema::table('auth_sessions', function (Blueprint $table) {
            $table->index('previous_token_hash');
        });

        DB::table('auth_sessions')->whereNull('rotated_at')->update([
            'rotated_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('auth_sessions', function (Blueprint $table) {
            $table->dropIndex(['previous_token_hash']);
            $table->dropColumn([
                'rotated_at',
                'previous_token_hash',
                'previous_csrf',
                'previous_expires_at',
            ]);
        });
    }
};
