<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->boolean('is_base')->default(false)->after('decimals');
            $table->decimal('rate', 16, 6)->default(1)->after('is_base');
        });

        Schema::dropIfExists('exchange_rates');
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['is_base', 'rate']);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_currency_id')->constrained('currencies');
            $table->foreignId('to_currency_id')->constrained('currencies');
            $table->decimal('rate', 16, 6);
            $table->date('date');
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['from_currency_id', 'to_currency_id', 'date']);
        });
    }
};
