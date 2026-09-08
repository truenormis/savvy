<?php

namespace App\Jobs;

use App\Models\TransactionImport;
use App\Models\Upload;
use App\Services\Import\CsvImportService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ImportSliceJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public string $importId,
        public string $uploadId,
        public int $start,
        public int $end,
        public bool $isFirstRange,
    ) {}

    public function handle(CsvImportService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $import = TransactionImport::find($this->importId);
        $upload = $this->uploadId ? Upload::find($this->uploadId) : null;

        if (! $import || ! $upload) {
            return;
        }

        $written = ['created' => 0, 'skipped' => 0, 'errors' => 0];
        $threshold = 10000;

        $apply = function (array $progress) use ($import, &$written) {
            $dc = $progress['created'] - $written['created'];
            $ds = $progress['skipped'] - $written['skipped'];
            $de = $progress['errors'] - $written['errors'];

            if ($dc === 0 && $ds === 0 && $de === 0) {
                return;
            }

            DB::table('transaction_imports')->where('id', $import->id)->update([
                'created_count' => DB::raw('created_count + '.$dc),
                'skipped_count' => DB::raw('skipped_count + '.$ds),
                'error_count' => DB::raw('error_count + '.$de),
                'processed_rows' => DB::raw('processed_rows + '.($dc + $ds + $de)),
            ]);

            $written = ['created' => $progress['created'], 'skipped' => $progress['skipped'], 'errors' => $progress['errors']];
        };

        // Throttle live-progress writes so parallel slices don't hammer the one import row.
        $result = $service->importSlice(
            $upload,
            $import->meta['parse_meta'] ?? [],
            $import->mapping ?? [],
            $import->options ?? [],
            $this->start,
            $this->end,
            $this->isFirstRange,
            $import->meta['entity_maps'] ?? ['categories' => [], 'tags' => []],
            function (array $progress) use ($apply, &$written, $threshold) {
                $pending = ($progress['created'] - $written['created'])
                    + ($progress['skipped'] - $written['skipped'])
                    + ($progress['errors'] - $written['errors']);

                if ($pending >= $threshold) {
                    $apply($progress);
                }
            },
        );

        // Authoritative final increment of whatever remained below the threshold.
        $apply(['created' => $result['created'], 'skipped' => $result['skipped'], 'errors' => count($result['errors'])]);
    }
}
