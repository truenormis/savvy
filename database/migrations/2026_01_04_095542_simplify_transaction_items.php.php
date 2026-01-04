<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->change();
        });
    }


    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 3)->default(1)->change();
            $table->string('unit', 20)->nullable();
        });
    }
};
