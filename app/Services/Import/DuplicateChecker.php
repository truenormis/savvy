<?php

namespace App\Services\Import;

use App\Models\Transaction;
use Illuminate\Support\Collection;

class DuplicateChecker
{
    private array $existingHashes = [];
    private array $importHashes = [];

    /**
     * Load existing transactions for duplicate checking
     */
    public function loadExistingTransactions(int $accountId, ?string $startDate = null, ?string $endDate = null): void
    {
        $query = Transaction::where('account_id', $accountId);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $transactions = $query->get(['id', 'date', 'amount', 'description']);

        $this->existingHashes = [];

        foreach ($transactions as $transaction) {
            $hash = $this->generateHash(
                $transaction->date,
                (float) $transaction->amount,
                $transaction->description
            );
            $this->existingHashes[$hash] = $transaction->id;
        }
    }

    /**
     * Check if transaction is a duplicate
     * Returns transaction ID if duplicate, null otherwise
     */
    public function isDuplicate(string|\DateTimeInterface $date, float $amount, ?string $description): ?int
    {
        $hash = $this->generateHash($date, $amount, $description);

        // Check against existing transactions
        if (isset($this->existingHashes[$hash])) {
            return $this->existingHashes[$hash];
        }

        // Check against transactions in current import batch
        if (isset($this->importHashes[$hash])) {
            return -1; // -1 indicates duplicate within import batch
        }

        return null;
    }

    /**
     * Mark transaction as being imported (for batch duplicate detection)
     */
    public function markAsImporting(string|\DateTimeInterface $date, float $amount, ?string $description): void
    {
        $hash = $this->generateHash($date, $amount, $description);
        $this->importHashes[$hash] = true;
    }

    /**
     * Reset import batch hashes
     */
    public function resetImportBatch(): void
    {
        $this->importHashes = [];
    }

    /**
     * Generate hash for duplicate detection
     */
    private function generateHash(string|\DateTimeInterface $date, float $amount, ?string $description): string
    {
        // Normalize date to Y-m-d format
        if ($date instanceof \DateTimeInterface) {
            $normalizedDate = $date->format('Y-m-d');
        } else {
            // Parse string date and normalize to Y-m-d
            $parsed = date_create($date);
            $normalizedDate = $parsed ? $parsed->format('Y-m-d') : $date;
        }

        // Normalize description: trim, lowercase, remove extra spaces
        $normalizedDescription = $description
            ? strtolower(trim(preg_replace('/\s+/', ' ', $description)))
            : '';

        // Round amount to 2 decimal places for comparison
        $normalizedAmount = round($amount, 2);

        return md5("{$normalizedDate}|{$normalizedAmount}|{$normalizedDescription}");
    }

    /**
     * Get statistics about loaded existing transactions
     */
    public function getExistingCount(): int
    {
        return count($this->existingHashes);
    }

    /**
     * Batch check multiple transactions for duplicates
     */
    public function batchCheck(array $transactions): array
    {
        $results = [];

        foreach ($transactions as $index => $transaction) {
            $duplicateId = $this->isDuplicate(
                $transaction['date'],
                $transaction['amount'],
                $transaction['description'] ?? null
            );

            $results[$index] = [
                'is_duplicate' => $duplicateId !== null,
                'duplicate_of' => $duplicateId > 0 ? $duplicateId : null,
                'is_duplicate_in_batch' => $duplicateId === -1,
            ];
        }

        return $results;
    }
}
