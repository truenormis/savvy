<?php

namespace App\Jobs;

use App\Models\TransactionImport;
use App\Models\Upload;
use App\Services\Import\CsvImportService;
use App\Services\Upload\UploadManager;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class RunImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public string $importId,
    ) {}

    public function handle(CsvImportService $service): void
    {
        $import = TransactionImport::find($this->importId);

        if (! $import) {
            return;
        }

        $upload = $import->upload_id ? Upload::find($import->upload_id) : null;

        if (! $upload || ! $upload->isCompleted()) {
            $import->update([
                'status' => TransactionImport::STATUS_FAILED,
                'message' => 'The uploaded file is no longer available.',
            ]);

            return;
        }

        $parseMeta = $import->meta['parse_meta'] ?? [];
        $mapping = $import->mapping ?? [];
        $options = $import->options ?? [];

        // Pre-create missing entities single-threaded so parallel slices never race.
        $entities = $service->collectEntities($upload, $parseMeta, $mapping, $options);

        $import->forceFill([
            'status' => TransactionImport::STATUS_IMPORTING,
            'processed_rows' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'meta' => array_merge($import->meta ?? [], [
                'entity_maps' => ['categories' => $entities['categories'], 'tags' => $entities['tags']],
                'created_categories' => $entities['created_categories'],
                'created_tags' => $entities['created_tags'],
            ]),
        ])->save();

        $ranges = $this->computeRanges((int) ($upload->size ?: 0));

        $jobs = [];
        foreach ($ranges as $index => [$start, $end]) {
            $jobs[] = new ImportSliceJob($import->id, $upload->id, $start, $end, $index === 0);
        }

        $importId = $import->id;
        $uploadId = $upload->id;

        Bus::batch($jobs)
            ->name("transaction-import:{$importId}")
            ->then(function (Batch $batch) use ($importId, $uploadId) {
                $import = TransactionImport::find($importId);

                if ($import) {
                    $import->forceFill([
                        'status' => TransactionImport::STATUS_COMPLETED,
                        'processed_rows' => $import->created_count + $import->skipped_count + $import->error_count,
                        'meta' => collect($import->meta ?? [])->except('entity_maps')->all(),
                    ])->save();
                }

                $upload = Upload::find($uploadId);

                if ($upload) {
                    app(UploadManager::class)->discard($upload);
                }
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($importId) {
                TransactionImport::whereKey($importId)->update([
                    'status' => TransactionImport::STATUS_FAILED,
                    'message' => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }

    /**
     * @return array<int,array{0:int,1:int}>
     */
    private function computeRanges(int $size): array
    {
        if ($size <= 0) {
            return [[0, PHP_INT_MAX]];
        }

        $sliceBytes = max(1, (int) config('uploads.import_slice_bytes', 16 * 1024 * 1024));
        $maxSlices = max(1, (int) config('uploads.import_max_slices', 2000));
        $slices = max(1, min($maxSlices, (int) ceil($size / $sliceBytes)));
        $per = (int) ceil($size / $slices);

        $ranges = [];
        for ($start = 0; $start < $size; $start += $per) {
            $ranges[] = [$start, min($start + $per, $size)];
        }

        return $ranges ?: [[0, $size]];
    }

    public function failed(\Throwable $e): void
    {
        TransactionImport::whereKey($this->importId)->update([
            'status' => TransactionImport::STATUS_FAILED,
            'message' => $e->getMessage(),
        ]);
    }
}
