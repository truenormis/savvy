<?php

namespace App\Services\Upload;

use App\Models\Upload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class UploadManager
{
    public function __construct(
        private LocalMultipartStore $store
    ) {}

    public function createMultipartUpload(?int $userId, string $bucketName, string $filename, ?int $size, ?string $mimeType): Upload
    {
        $bucket = $this->bucket($bucketName);

        $this->guard($bucket, $filename, $size, $mimeType);

        $partSize = $this->effectivePartSize($bucket, $size);
        $ttl = (int) config('uploads.url_ttl');

        $upload = new Upload([
            'user_id' => $userId,
            'bucket' => $bucketName,
            'disk' => config('uploads.disk'),
            'original_name' => $filename,
            'mime_type' => $mimeType,
            'size' => $size,
            'part_size' => $partSize,
            'total_parts' => $size !== null ? max(1, (int) ceil($size / $partSize)) : null,
            'status' => Upload::STATUS_PENDING,
            'expires_at' => CarbonImmutable::now()->addSeconds($ttl),
        ]);

        $upload->id = $upload->newUniqueId();
        $upload->object_key = $this->buildObjectKey($bucket, $upload->id, $filename);
        $upload->save();

        return $upload;
    }

    /**
     * @return array{url:string,method:string,headers:array<string,string>,expires:int}
     */
    public function signPart(Upload $upload, int $partNumber): array
    {
        if (! $upload->isPending()) {
            throw new UploadException('This upload is no longer accepting parts.', 409);
        }

        $maxParts = (int) config('uploads.max_parts');

        if ($partNumber < 1 || $partNumber > $maxParts) {
            throw new UploadException('Part number is out of range.');
        }

        $ttl = (int) config('uploads.url_ttl');
        $expiresAt = CarbonImmutable::now()->addSeconds($ttl);

        return [
            'url' => URL::temporarySignedRoute('uploads.part', $expiresAt, [
                'upload' => $upload->id,
                'partNumber' => $partNumber,
            ]),
            'method' => 'PUT',
            'headers' => [],
            'expires' => $ttl,
        ];
    }

    /**
     * @param  resource  $stream
     * @return array{size:int,etag:string}
     */
    public function storePart(Upload $upload, int $partNumber, $stream): array
    {
        if (! $upload->isPending()) {
            throw new UploadException('This upload is no longer accepting parts.', 409);
        }

        $maxParts = (int) config('uploads.max_parts');

        if ($partNumber < 1 || $partNumber > $maxParts) {
            throw new UploadException('Part number is out of range.');
        }

        return $this->store->putPart($upload->disk, $upload->id, $partNumber, $stream);
    }

    /**
     * @return array<int,array{PartNumber:int,Size:int,ETag:string}>
     */
    public function listParts(Upload $upload): array
    {
        return $this->store->listParts($upload->disk, $upload->id);
    }

    /**
     * @param  array<int,array{number:int|string,etag?:string|null}>  $parts
     */
    public function completeMultipartUpload(Upload $upload, array $parts): Upload
    {
        if ($upload->isCompleted()) {
            return $upload;
        }

        if (! $upload->isPending()) {
            throw new UploadException('This upload cannot be completed.', 409);
        }

        if ($parts === []) {
            throw new UploadException('At least one part is required to complete an upload.');
        }

        $ordered = collect($parts)
            ->map(fn ($part) => [
                'number' => (int) $part['number'],
                'etag' => $part['etag'] ?? null,
            ])
            ->sortBy('number')
            ->values()
            ->all();

        $this->store->assemble($upload->disk, $upload->id, $upload->object_key, $ordered);

        $upload->forceFill([
            'path' => $upload->object_key,
            'total_parts' => count($ordered),
            'status' => Upload::STATUS_COMPLETED,
            'completed_at' => CarbonImmutable::now(),
        ])->save();

        return $upload;
    }

    public function abortMultipartUpload(Upload $upload): void
    {
        $this->store->purge($upload->disk, $upload->id, $upload->object_key);

        $upload->forceFill(['status' => Upload::STATUS_ABORTED])->save();
    }

    public function location(Upload $upload): string
    {
        return URL::to("/api/s3/{$upload->bucket}/".ltrim($upload->object_key, '/'));
    }

    public function read(Upload $upload): string
    {
        $this->assertReadable($upload);

        return $this->store->get($upload->disk, $upload->path);
    }

    public function readStream(Upload $upload)
    {
        $this->assertReadable($upload);

        return $this->store->readStream($upload->disk, $upload->path);
    }

    public function discard(Upload $upload): void
    {
        $this->store->purge($upload->disk, $upload->id, $upload->object_key);

        $upload->forceFill(['status' => Upload::STATUS_CONSUMED])->save();
    }

    private function assertReadable(Upload $upload): void
    {
        if (! $upload->isCompleted() || ! $upload->path) {
            throw new UploadException('Upload is not ready to be read.', 409);
        }
    }

    private function effectivePartSize(array $bucket, ?int $size): int
    {
        $base = (int) ($bucket['part_size'] ?? config('uploads.part_size'));

        if ($size === null) {
            return $base;
        }

        $maxParts = (int) config('uploads.max_parts');
        $needed = (int) ceil($size / max(1, $maxParts));

        if ($needed <= $base) {
            return $base;
        }

        $mib = 1024 * 1024;

        return (int) (ceil($needed / $mib) * $mib);
    }

    private function buildObjectKey(array $bucket, string $uploadId, string $filename): string
    {
        $prefix = trim($bucket['prefix'] ?? '', '/');
        $date = CarbonImmutable::now()->format('Y/m/d');
        $name = $this->safeName($filename);

        return implode('/', array_filter([$prefix, $date, $uploadId, $name]));
    }

    private function safeName(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $slug = Str::slug($name) ?: 'file';

        return $ext ? "{$slug}.{$ext}" : $slug;
    }

    private function guard(array $bucket, string $filename, ?int $size, ?string $mimeType): void
    {
        if ($size !== null) {
            if ($size < 1) {
                throw new UploadException('File is empty.');
            }

            $maxSize = (int) ($bucket['max_size'] ?? PHP_INT_MAX);

            if ($size > $maxSize) {
                throw new UploadException('File exceeds the maximum allowed size of '.$this->humanBytes($maxSize).'.');
            }
        }

        $extensions = $bucket['allowed_extensions'] ?? [];

        if ($extensions !== []) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (! in_array($extension, $extensions, true)) {
                throw new UploadException('File type is not allowed. Allowed: '.implode(', ', $extensions).'.');
            }
        }

        $mimeTypes = $bucket['allowed_mime_types'] ?? [];

        if ($mimeTypes !== [] && $mimeType !== null && $mimeType !== '' && ! in_array($mimeType, $mimeTypes, true)) {
            throw new UploadException('File content type is not allowed.');
        }
    }

    private function bucket(string $name): array
    {
        $bucket = config("uploads.buckets.{$name}");

        if (! is_array($bucket)) {
            throw new UploadException("Unknown bucket [{$name}].", 404);
        }

        return $bucket;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }
}
