<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionService;
use Illuminate\Console\Command;

class ProcessRecurringTransactions extends Command
{
    protected $signature = 'recurring:process';

    protected $description = 'Generate transactions from due recurring templates';

    public function handle(RecurringTransactionService $service): int
    {
        $this->info('Processing recurring transactions...');

        $generated = $service->processDue();
        $count = count($generated);

        if ($count > 0) {
            $this->info("Generated {$count} transaction(s)");
        } else {
            $this->info('No recurring transactions due');
        }

        return Command::SUCCESS;
    }
}
