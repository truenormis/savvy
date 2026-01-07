<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupService
{
    public function create(?string $note = null): Backup
    {
        $filename = 'backup-' . now()->format('Y-m-d-H-i-s') . '.sqlite';
        $path = config('backup.path');

        File::ensureDirectoryExists($path);
        File::copy(config('database.connections.sqlite.database'), "$path/$filename");

        return Backup::create([
            'filename' => $filename,
            'size' => File::size("$path/$filename"),
            'note' => $note,
        ]);
    }

    public function upload(UploadedFile $file, ?string $note = null): Backup
    {
        $filename = 'backup-' . now()->format('Y-m-d-H-i-s') . '.sqlite';
        $path = config('backup.path');

        File::ensureDirectoryExists($path);
        $file->move($path, $filename);

        return Backup::create([
            'filename' => $filename,
            'size' => File::size("$path/$filename"),
            'note' => $note ?? 'Uploaded',
        ]);
    }

    public function list(): Collection
    {
        return Backup::orderByDesc('created_at')->get();
    }

    public function download(Backup $backup): StreamedResponse
    {
        $path = $this->getPath($backup->filename);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, $backup->filename, [
            'Content-Type' => 'application/x-sqlite3',
        ]);
    }

    public function restore(Backup $backup): void
    {
        $backupPath = $this->getPath($backup->filename);
        $dbPath = config('database.connections.sqlite.database');

        DB::disconnect();
        File::copy($backupPath, $dbPath);
        DB::reconnect();
    }

    public function delete(Backup $backup): void
    {
        $path = $this->getPath($backup->filename);

        if (File::exists($path)) {
            File::delete($path);
        }

        $backup->delete();
    }

    public function getPath(string $filename): string
    {
        return config('backup.path') . '/' . $filename;
    }
}
