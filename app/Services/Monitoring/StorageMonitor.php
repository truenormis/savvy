<?php

namespace App\Services\Monitoring;

use App\Models\TransactionImport;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;

class StorageMonitor
{
    private const ON_DISK_STATUSES = [Upload::STATUS_PENDING, Upload::STATUS_COMPLETED];

    public function snapshot(): array
    {
        $usage = Upload::query()
            ->selectRaw('bucket, status, COUNT(*) as count, COALESCE(SUM(size), 0) as bytes')
            ->groupBy('bucket', 'status')
            ->get();

        return [
            'volume' => $this->volume(),
            'managed' => $this->managed($usage),
            'uploads' => $this->uploads($usage),
            'imports' => $this->imports(),
        ];
    }

    private function volume(): array
    {
        $disk = (string) config('uploads.disk');
        $root = rtrim(Storage::disk($disk)->path(''), DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
        $probe = $this->resolveExisting($root);

        $total = @disk_total_space($probe);
        $free = @disk_free_space($probe);

        $total = is_numeric($total) ? (int) $total : null;
        $free = is_numeric($free) ? (int) $free : null;
        $used = ($total !== null && $free !== null) ? max(0, $total - $free) : null;

        return [
            'disk' => $disk,
            'path' => $root,
            'total_bytes' => $total,
            'free_bytes' => $free,
            'used_bytes' => $used,
            'used_percent' => ($total && $used !== null) ? round($used / $total * 100, 1) : null,
        ];
    }

    private function managed(\Illuminate\Support\Collection $usage): array
    {
        $onDisk = $usage->whereIn('status', self::ON_DISK_STATUSES);

        $buckets = $onDisk
            ->groupBy('bucket')
            ->map(fn ($rows, $bucket) => [
                'bucket' => $bucket,
                'bytes' => (int) $rows->sum('bytes'),
                'objects' => (int) $rows->where('status', Upload::STATUS_COMPLETED)->sum('count'),
            ])
            ->sortByDesc('bytes')
            ->values()
            ->all();

        return [
            'used_bytes' => (int) $onDisk->sum('bytes'),
            'objects' => (int) $usage->where('status', Upload::STATUS_COMPLETED)->sum('count'),
            'pending_bytes' => (int) $usage->where('status', Upload::STATUS_PENDING)->sum('bytes'),
            'buckets' => $buckets,
        ];
    }

    private function uploads(\Illuminate\Support\Collection $usage): array
    {
        $byStatus = $usage
            ->groupBy('status')
            ->map(fn ($rows) => (int) $rows->sum('count'));

        return [
            'total' => (int) $byStatus->sum(),
            'by_status' => $byStatus->all(),
        ];
    }

    private function imports(): array
    {
        $byStatus = TransactionImport::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count) => (int) $count);

        return [
            'total' => (int) $byStatus->sum(),
            'by_status' => $byStatus->all(),
        ];
    }

    private function resolveExisting(string $path): string
    {
        $current = $path;

        while ($current !== '' && ! is_dir($current)) {
            $parent = \dirname($current);

            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        return $current !== '' && is_dir($current) ? $current : DIRECTORY_SEPARATOR;
    }
}
