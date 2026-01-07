<?php

namespace App\Console\Commands;

use App\Services\CurrencyApiService;
use Illuminate\Console\Command;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'currencies:update';

    protected $description = 'Update currency exchange rates from API';

    public function handle(CurrencyApiService $service): int
    {
        $this->info('Fetching currency rates...');

        $result = $service->updateRates();

        if ($result['status'] === 'success') {
            $this->info($result['message']);
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        return Command::FAILURE;
    }
}
