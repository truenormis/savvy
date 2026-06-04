<?php

namespace App\Services\Upload;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class LocalMultipartStore
{
    public function partPath(string $uploadId, int $partNumber): string
    {
        return ".multipart/{$uploadId}/{$partNumber}.part";
    }

    /**
     * @param  resource  $stream
     * @return array{size:int,etag:string}
     */
    public function putPart(string $disk, string $uploadId, int $partNumber, $stream): array
    {
        $path = $this->partPath($uploadId, $partNumber);
        $fs = $this->disk($disk);

        $fs->writeStream($path, $stream);

        $absolute = $fs->path($path);

        return [
            'size' => (int) filesize($absolute),
            'etag' => md5_file($absolute),
        ];
    }

    public function partExists(string $disk, string $uploadId, int $partNumber): bool
    {
        return $this->disk($disk)->exists($this->partPath($uploadId, $partNumber));
    }

    /**
     * @return array<int,array{PartNumber:int,Size:int,ETag:string}>
     */
    public function listParts(string $disk, string $uploadId): array
    {
        $fs = $this->disk($disk);
        $files = $fs->files(".multipart/{$uploadId}");
        $parts = [];

        foreach ($files as $file) {
            if (! str_ends_with($file, '.part')) {
                continue;
            }

            $partNumber = (int) pathinfo($file, PATHINFO_FILENAME);
            $absolute = $fs->path($file);

            $parts[] = [
                'PartNumber' => $partNumber,
                'Size' => (int) filesize($absolute),
                'ETag' => '"'.md5_file($absolute).'"',
            ];
        }

        usort($parts, fn ($a, $b) => $a['PartNumber'] <=> $b['PartNumber']);

        return $parts;
    }

    /**
     * Assemble staged parts into the final object at $objectKey.
     *
     * @param  array<int,array{number:int,etag:?string}>  $parts  ordered ascending by number
     */
    public function assemble(string $disk, string $uploadId, string $objectKey, array $parts): void
    {
        $fs = $this->disk($disk);

        $directory = trim(dirname($objectKey), '.');

        if ($directory !== '') {
            $fs->makeDirectory($directory);
        }

        $out = fopen($fs->path($objectKey), 'wb');

        if ($out === false) {
            throw new UploadException('Unable to assemble upload.', 500);
        }

        $verifyEtags = (bool) config('uploads.verify_etags', false);

        try {
            foreach ($parts as $part) {
                $partPath = $this->partPath($uploadId, $part['number']);

                if (! $fs->exists($partPath)) {
                    throw new UploadException("Part {$part['number']} was never uploaded.", 422);
                }

                if ($verifyEtags && ! empty($part['etag'])) {
                    $expected = strtolower(trim($part['etag'], '"'));
                    $actual = md5_file($fs->path($partPath));

                    if ($expected !== $actual) {
                        throw new UploadException("ETag mismatch for part {$part['number']}.", 422);
                    }
                }

                $in = $fs->readStream($partPath);
                stream_copy_to_stream($in, $out, 1 << 21);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        $this->deleteStaging($disk, $uploadId);
    }

    public function deleteStaging(string $disk, string $uploadId): void
    {
        $this->disk($disk)->deleteDirectory(".multipart/{$uploadId}");
    }

    public function purge(string $disk, string $uploadId, ?string $objectKey = null): void
    {
        $fs = $this->disk($disk);
        $fs->deleteDirectory(".multipart/{$uploadId}");

        if ($objectKey !== null && $fs->exists($objectKey)) {
            $fs->delete($objectKey);
        }
    }

    public function exists(string $disk, string $path): bool
    {
        return $this->disk($disk)->exists($path);
    }

    public function get(string $disk, string $path): string
    {
        return (string) $this->disk($disk)->get($path);
    }

    /**
     * @return resource|null
     */
    public function readStream(string $disk, string $path)
    {
        return $this->disk($disk)->readStream($path);
    }

    private function disk(string $disk): Filesystem
    {
        return Storage::disk($disk);
    }
}
