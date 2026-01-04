<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimals' => 2,
                'is_base' => true,
                'rate' => 1.000000,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'decimals' => 2,
                'is_base' => false,
                'rate' => 1.080000,
            ],
            [
                'code' => 'UAH',
                'name' => 'Ukrainian Hryvnia',
                'symbol' => '₴',
                'decimals' => 2,
                'is_base' => false,
                'rate' => 0.024000,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
