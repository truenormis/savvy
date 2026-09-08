<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give transactions written before 1.3 the dedup hash the CSV importer uses.
 *
 * The importer drops duplicates purely through the unique index on
 * (account_id, dedup_hash). Rows created before that column existed carry
 * NULL, and SQLite treats NULLs as distinct, so nothing imported could ever
 * collide with them: re-importing a statement already imported under 1.2
 * silently doubled the ledger, even though the preview screen counted those
 * rows as duplicates it would skip.
 *
 * Genuine repeats (the same amount, day and description on one account - two
 * identical coffees) hash alike, and the index is unique, so only the first
 * row of each group can carry the hash. Later ones stay NULL, exactly as they
 * are today: no row is lost and no constraint can fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        $chunk = 1000;
        $lastId = 0;

        do {
            $rows = DB::table('transactions')
                ->select('id', 'account_id', 'date', 'amount', 'description')
                ->whereNull('dedup_hash')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $candidates = [];

            foreach ($rows as $row) {
                $lastId = $row->id;
                $candidates[] = [
                    'id' => $row->id,
                    'account_id' => $row->account_id,
                    'hash' => $this->dedupHash($row->date, (float) $row->amount, $row->description),
                ];
            }

            // Pairs already carrying this hash, from an earlier chunk or from
            // rows the importer itself wrote. Looked up per chunk so the
            // migration stays bounded in memory on large ledgers.
            $taken = [];

            foreach (DB::table('transactions')
                ->select('account_id', 'dedup_hash')
                ->whereIn('dedup_hash', array_unique(array_column($candidates, 'hash')))
                ->get() as $existing) {
                $taken[$existing->account_id.'|'.$existing->dedup_hash] = true;
            }

            $updates = [];

            foreach ($candidates as $candidate) {
                $key = $candidate['account_id'].'|'.$candidate['hash'];

                if (isset($taken[$key])) {
                    continue;
                }

                $taken[$key] = true;
                $updates[$candidate['hash']][] = $candidate['id'];
            }

            foreach ($updates as $hash => $ids) {
                DB::table('transactions')->whereIn('id', $ids)->update(['dedup_hash' => $hash]);
            }
        } while ($rows->count() === $chunk);
    }

    public function down(): void
    {
        // The column is dropped wholesale by the migration that added it.
    }

    /**
     * Must stay identical to CsvImportService::dedupHash().
     */
    private function dedupHash(string $date, float $amount, ?string $description): string
    {
        $normalizedDescription = $description
            ? strtolower(trim(preg_replace('/\s+/', ' ', $description)))
            : '';

        return md5(substr($date, 0, 10).'|'.round($amount, 2).'|'.$normalizedDescription);
    }
};
