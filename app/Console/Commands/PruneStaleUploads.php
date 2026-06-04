<?php

namespace App\Console\Commands;

use App\Models\Upload;
use App\Services\Upload\UploadManager;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PruneStaleUploads extends Command
{
    protected $signature = 'uploads:prune {--hours=24 : Age threshold for finished uploads}';

    protected $description = 'Abort expired pending multipart uploads and purge their staged parts from the volume';

    public function handle(UploadManager $manager): int
    {
        $now = CarbonImmutable::now();
        $aborted = 0;

        Upload::where('status', Upload::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->chunkById(200, function ($uploads) use ($manager, &$aborted) {
                foreach ($uploads as $upload) {
                    $manager->abortMultipartUpload($upload);
                    $aborted++;
                }
            });

        $cutoff = $now->subHours((int) $this->option('hours'));

        $deleted = Upload::whereIn('status', [
            Upload::STATUS_ABORTED,
            Upload::STATUS_CONSUMED,
        ])->where('updated_at', '<', $cutoff)->delete();

        $this->info("Aborted {$aborted} stale upload(s); pruned {$deleted} finished record(s).");

        return self::SUCCESS;
    }
}
