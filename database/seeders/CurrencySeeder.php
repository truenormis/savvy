<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Services\CurrencyApiService;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        // Fallback rates: "1 currency = X USD" (for multiplication to get base amount)
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'is_base' => true, 'rate' => 1.000000],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimals' => 2, 'is_base' => false, 'rate' => 1.08],      // 1 EUR = 1.08 USD
            ['code' => 'UAH', 'name' => 'Ukrainian Hryvnia', 'symbol' => '₴', 'decimals' => 2, 'is_base' => false, 'rate' => 0.024],    // 1 UAH = 0.024 USD
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimals' => 2, 'is_base' => false, 'rate' => 1.27],      // 1 GBP = 1.27 USD
            ['code' => 'PLN', 'name' => 'Polish Zloty', 'symbol' => 'zł', 'decimals' => 2, 'is_base' => false, 'rate' => 0.25],     // 1 PLN = 0.25 USD
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'decimals' => 0, 'is_base' => false, 'rate' => 0.0064],   // 1 JPY = 0.0064 USD
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'Fr', 'decimals' => 2, 'is_base' => false, 'rate' => 1.12],     // 1 CHF = 1.12 USD
        ];

        // Сначала сбрасываем базовую валюту для всех
        Currency::query()->update(['is_base' => false]);

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }

        $this->command->info('Created ' . count($currencies) . ' currencies.');

        // Автоматическое обновление курсов через API
        try {
            $apiService = app(CurrencyApiService::class);
            $result = $apiService->updateRates();

            $this->command->info("Currency rates: {$result['message']}");
        } catch (\Exception $e) {
            $this->command->warn("Could not update rates from API: {$e->getMessage()}");
            $this->command->info('Using fallback rates.');
        }
    }
}
