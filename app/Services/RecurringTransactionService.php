<?php

namespace App\Services;

use App\DTOs\TransactionData;
use App\Enums\RecurringFrequency;
use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringTransactionService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    public function getAll(): Collection
    {
        return RecurringTransaction::with(['account.currency', 'toAccount.currency', 'category', 'tags'])
            ->orderBy('next_run_date')
            ->get();
    }

    public function findOrFail(int $id): RecurringTransaction
    {
        return RecurringTransaction::with(['account.currency', 'toAccount.currency', 'category', 'tags'])
            ->findOrFail($id);
    }

    public function getUpcoming(int $limit = 5): Collection
    {
        return RecurringTransaction::with(['account.currency', 'category'])
            ->active()
            ->orderBy('next_run_date')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): RecurringTransaction
    {
        return DB::transaction(function () use ($data) {
            $data['next_run_date'] = $this->calculateInitialNextRunDate($data);

            $recurring = RecurringTransaction::create($data);

            if (!empty($data['tag_ids'])) {
                $recurring->tags()->sync($data['tag_ids']);
            }

            return $recurring->load(['account.currency', 'toAccount.currency', 'category', 'tags']);
        });
    }

    public function update(RecurringTransaction $recurring, array $data): RecurringTransaction
    {
        return DB::transaction(function () use ($recurring, $data) {
            // Recalculate next_run_date if schedule changed
            if ($this->scheduleChanged($recurring, $data)) {
                $data['next_run_date'] = $this->calculateInitialNextRunDate($data);
            }

            $recurring->update($data);

            if (array_key_exists('tag_ids', $data)) {
                $recurring->tags()->sync($data['tag_ids'] ?? []);
            }

            return $recurring->load(['account.currency', 'toAccount.currency', 'category', 'tags']);
        });
    }

    public function delete(RecurringTransaction $recurring): void
    {
        $recurring->delete();
    }

    public function skip(RecurringTransaction $recurring): RecurringTransaction
    {
        $recurring->update([
            'next_run_date' => $this->calculateNextRunDate($recurring),
        ]);

        return $recurring->fresh(['account.currency', 'toAccount.currency', 'category', 'tags']);
    }

    public function processDue(): array
    {
        $due = RecurringTransaction::due()->get();
        $generated = [];

        foreach ($due as $recurring) {
            $transaction = $this->generateTransaction($recurring);
            $generated[] = $transaction;

            $recurring->update([
                'last_run_date' => now()->toDateString(),
                'next_run_date' => $this->calculateNextRunDate($recurring),
            ]);
        }

        return $generated;
    }

    public function generateTransaction(RecurringTransaction $recurring): Transaction
    {
        $toAmount = null;

        if ($recurring->isTransfer() && $recurring->to_amount) {
            $toAmount = $recurring->to_amount;
        } elseif ($recurring->isTransfer()) {
            $toAmount = $this->calculateToAmount($recurring);
        }

        $data = new TransactionData(
            type: $recurring->type,
            accountId: $recurring->account_id,
            amount: (float) $recurring->amount,
            categoryId: $recurring->category_id,
            toAccountId: $recurring->to_account_id,
            toAmount: $toAmount,
            description: $recurring->description,
            date: now()->toDateString(),
            tagIds: $recurring->tags->pluck('id')->toArray(),
        );

        return $this->transactionService->create($data);
    }

    public function calculateNextRunDate(RecurringTransaction $recurring): Carbon
    {
        $current = $recurring->next_run_date->copy();
        $interval = $recurring->interval;

        return match ($recurring->frequency) {
            RecurringFrequency::Daily => $current->addDays($interval),
            RecurringFrequency::Weekly => $this->calculateNextWeekly($current, $interval, $recurring->day_of_week),
            RecurringFrequency::Monthly => $this->calculateNextMonthly($current, $interval, $recurring->day_of_month),
            RecurringFrequency::Yearly => $current->addYears($interval),
        };
    }

    protected function calculateInitialNextRunDate(array $data): string
    {
        $startDate = Carbon::parse($data['start_date']);
        $frequency = RecurringFrequency::from($data['frequency']);
        $interval = $data['interval'] ?? 1;

        // If start date is in the future, use it
        if ($startDate->isFuture() || $startDate->isToday()) {
            return $this->adjustToSchedule($startDate, $frequency, $data)->toDateString();
        }

        // Calculate from start date until we reach future
        $nextDate = $startDate->copy();

        while ($nextDate->isPast()) {
            $nextDate = match ($frequency) {
                RecurringFrequency::Daily => $nextDate->addDays($interval),
                RecurringFrequency::Weekly => $nextDate->addWeeks($interval),
                RecurringFrequency::Monthly => $nextDate->addMonths($interval),
                RecurringFrequency::Yearly => $nextDate->addYears($interval),
            };
        }

        return $this->adjustToSchedule($nextDate, $frequency, $data)->toDateString();
    }

    protected function adjustToSchedule(Carbon $date, RecurringFrequency $frequency, array $data): Carbon
    {
        if ($frequency === RecurringFrequency::Weekly && isset($data['day_of_week'])) {
            $dayOfWeek = (int) $data['day_of_week'];
            if ($date->dayOfWeek !== $dayOfWeek) {
                $date = $date->next($dayOfWeek);
            }
        }

        if ($frequency === RecurringFrequency::Monthly && isset($data['day_of_month'])) {
            $dayOfMonth = min((int) $data['day_of_month'], $date->daysInMonth);
            $date = $date->day($dayOfMonth);
            if ($date->isPast() && !$date->isToday()) {
                $date = $date->addMonth()->day(min((int) $data['day_of_month'], $date->daysInMonth));
            }
        }

        return $date;
    }

    protected function calculateNextWeekly(Carbon $current, int $interval, ?int $dayOfWeek): Carbon
    {
        $next = $current->addWeeks($interval);

        if ($dayOfWeek !== null && $next->dayOfWeek !== $dayOfWeek) {
            $next = $next->next($dayOfWeek);
        }

        return $next;
    }

    protected function calculateNextMonthly(Carbon $current, int $interval, ?int $dayOfMonth): Carbon
    {
        $next = $current->addMonthsNoOverflow($interval);

        if ($dayOfMonth !== null) {
            $day = min($dayOfMonth, $next->daysInMonth);
            $next = $next->day($day);
        }

        return $next;
    }

    protected function calculateToAmount(RecurringTransaction $recurring): float
    {
        $fromAccount = Account::with('currency')->find($recurring->account_id);
        $toAccount = Account::with('currency')->find($recurring->to_account_id);

        if ($fromAccount->currency_id === $toAccount->currency_id) {
            return (float) $recurring->amount;
        }

        return $fromAccount->currency->convertTo((float) $recurring->amount, $toAccount->currency);
    }

    protected function scheduleChanged(RecurringTransaction $recurring, array $data): bool
    {
        $fields = ['frequency', 'interval', 'day_of_week', 'day_of_month', 'start_date'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data) && $recurring->$field != $data[$field]) {
                return true;
            }
        }

        return false;
    }
}
