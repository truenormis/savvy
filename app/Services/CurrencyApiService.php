<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyApiService
{
    protected string $apiUrl = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies';

    public function updateRates(): array
    {
        if (!settings('auto_update_currencies', true)) {
            return ['status' => 'skipped', 'message' => 'Auto-update disabled'];
        }

        $baseCurrency = Currency::getBase();

        if (!$baseCurrency) {
            return ['status' => 'error', 'message' => 'No base currency set'];
        }

        $baseCode = strtolower($baseCurrency->code);
        $apiData = $this->fetchRates($baseCode);

        if ($apiData && isset($apiData[$baseCode])) {
            return $this->updateFromBase($apiData[$baseCode], $baseCurrency);
        }

        // Fallback: find a reference currency that exists in API
        return $this->updateFromReference($baseCurrency);
    }

    protected function fetchRates(string $code): ?array
    {
        try {
            $response = Http::timeout(30)->get("{$this->apiUrl}/{$code}.json");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Currency API request failed for {$code}", [
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Currency API error: {$e->getMessage()}");
            return null;
        }
    }

    protected function updateFromBase(array $apiRates, Currency $baseCurrency): array
    {
        $currencies = Currency::where('id', '!=', $baseCurrency->id)->get();
        $updated = 0;
        $skipped = 0;

        foreach ($currencies as $currency) {
            $code = strtolower($currency->code);

            if (isset($apiRates[$code]) && $apiRates[$code] > 0) {
                // API returns "1 base = X currency", we need "1 currency = X base"
                $currency->update(['rate' => 1 / (float) $apiRates[$code]]);
                $updated++;
            } else {
                $skipped++;
            }
        }

        return [
            'status' => 'success',
            'message' => "Updated {$updated} currencies, skipped {$skipped}",
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    protected function updateFromReference(Currency $baseCurrency): array
    {
        // Try common currencies as reference
        $referenceCodes = ['usd', 'eur', 'gbp', 'jpy', 'cny'];
        $currencies = Currency::all();

        foreach ($referenceCodes as $refCode) {
            $refCurrency = $currencies->first(fn($c) => strtolower($c->code) === $refCode);

            if (!$refCurrency) {
                continue;
            }

            $apiData = $this->fetchRates($refCode);

            if (!$apiData || !isset($apiData[$refCode])) {
                continue;
            }

            $apiRates = $apiData[$refCode];
            $baseCode = strtolower($baseCurrency->code);

            // Check if base currency exists in this API response
            if (!isset($apiRates[$baseCode])) {
                continue;
            }

            // Calculate rates relative to our base currency
            // If API says 1 USD = X base_currency, then:
            // For any currency Y: rate = apiRates[Y] / apiRates[base]
            $baseRateInRef = $apiRates[$baseCode];

            $updated = 0;
            $skipped = 0;

            foreach ($currencies as $currency) {
                if ($currency->is_base) {
                    continue;
                }

                $code = strtolower($currency->code);

                if (isset($apiRates[$code]) && $apiRates[$code] > 0) {
                    // API returns "1 ref = X currency", we need "1 currency = X base"
                    // rate = baseRateInRef / apiRates[code]
                    $newRate = $baseRateInRef / $apiRates[$code];
                    $currency->update(['rate' => $newRate]);
                    $updated++;
                } else {
                    $skipped++;
                }
            }

            return [
                'status' => 'success',
                'message' => "Updated {$updated} currencies via {$refCode}, skipped {$skipped}",
                'updated' => $updated,
                'skipped' => $skipped,
                'reference' => $refCode,
            ];
        }

        return [
            'status' => 'error',
            'message' => 'No matching currencies found in API',
        ];
    }
}
