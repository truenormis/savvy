<?php

namespace App\Listeners;

use App\Events\SettingUpdated;
use App\Services\CurrencyApiService;

class RefreshCurrencyRates
{
    public function __construct(private CurrencyApiService $currencyApi) {}

    public function handle(SettingUpdated $event): void
    {
        if ($event->key !== 'auto_update_currencies' || ! $event->value) {
            return;
        }

        $this->currencyApi->updateRates();
    }
}
