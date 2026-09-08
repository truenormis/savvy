<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Reference data only. DemoSeeder is deliberately NOT listed here: it
     * wipes accounts, transactions, budgets, recurring transactions and
     * automation rules, and it creates demo logins with published passwords.
     * Run it explicitly with `php artisan db:seed --class=DemoSeeder`.
     */
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            CategorySeeder::class,
            TagSeeder::class,
        ]);
    }
}
