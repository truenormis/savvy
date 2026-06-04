<?php

namespace App\Jobs;

use App\Models\TransactionImport;
use App\Models\Upload;
use App\Services\Import\CsvImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ParseImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public string $importId,
        public string $uploadId,
    ) {}

    public function handle(CsvImportService $service): void
    {
        $import = TransactionImport::find($this->importId);

        if (! $import) {
            return;
        }

        $upload = Upload::find($this->uploadId);

        if (! $upload || ! $upload->isCompleted()) {
            $import->update([
                'status' => TransactionImport::STATUS_FAILED,
                'message' => 'Uploaded file is not available.',
            ]);

            return;
        }

        $import->update(['status' => TransactionImport::STATUS_PARSING]);

        $payload = $service->parseUpload($upload, $import->id);

        $import->update([
            'status' => TransactionImport::STATUS_PARSED,
            'total_rows' => $payload['total_rows'],
            'meta' => [
                'headers' => $payload['headers'],
                'preview_rows' => $payload['preview_rows'],
                'detected_formats' => $payload['detected_formats'],
                'suggested_mapping' => $payload['suggested_mapping'],
                'parse_meta' => $payload['parse_meta'],
            ],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        TransactionImport::whereKey($this->importId)->update([
            'status' => TransactionImport::STATUS_FAILED,
            'message' => $e->getMessage(),
        ]);
    }
}
