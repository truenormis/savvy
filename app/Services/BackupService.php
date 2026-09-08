<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupService
{
    public function create(?string $note = null): Backup
    {
        $filename = 'backup-'.now()->format('Y-m-d-H-i-s').'.sqlite';

        return $this->snapshot($filename, $note);
    }

    public function upload(UploadedFile $file, ?string $note = null): Backup
    {
        $filename = 'backup-'.now()->format('Y-m-d-H-i-s').'.sqlite';
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

    /**
     * Replace the live database with a backup.
     *
     * A backup may have been taken by an older version of Savvy, so the copy
     * alone is not enough: it rewinds the schema and the migration ledger.
     * Restoring therefore snapshots the current database first, swaps the
     * file, drops the stale WAL sidecars, migrates the restored database back
     * up to the running version, and clears state that describes the database
     * that was just replaced.
     */
    public function restore(Backup $backup): void
    {
        $backupPath = $this->getPath($backup->filename);
        $dbPath = $this->databasePath();

        // Never make a restore irreversible.
        $this->snapshot(
            'backup-'.now()->format('Y-m-d-H-i-s').'-pre-restore.sqlite',
            'Automatic snapshot taken before restoring '.$backup->filename
        );

        $this->checkpoint();
        DB::disconnect();

        File::copy($backupPath, $dbPath);

        // The sidecars still hold frames of the database we just replaced.
        // SQLite would replay them over the restored file on the next open.
        foreach (['-wal', '-shm'] as $suffix) {
            if (File::exists($dbPath.$suffix)) {
                File::delete($dbPath.$suffix);
            }
        }

        DB::reconnect();

        // Bring an older backup forward to the running schema, and recreate
        // any framework table the restored ledger no longer knows about.
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('app:ensure-shards');

        $this->flushReplacedState();
        $this->reconcileIndex();
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
        return config('backup.path').'/'.$filename;
    }

    private function snapshot(string $filename, ?string $note): Backup
    {
        $path = config('backup.path');

        File::ensureDirectoryExists($path);

        // In WAL mode the most recent writes live in the -wal sidecar. Copying
        // the main file without folding them in silently loses them.
        $this->checkpoint();

        File::copy($this->databasePath(), "$path/$filename");

        return Backup::create([
            'filename' => $filename,
            'size' => File::size("$path/$filename"),
            'note' => $note,
        ]);
    }

    private function checkpoint(): void
    {
        try {
            DB::select('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (Throwable) {
            // Not a WAL database, or another connection holds the write lock.
            // The copy is still valid for non-WAL journals.
        }
    }

    private function databasePath(): string
    {
        return config('database.connections.'.config('database.default').'.database')
            ?: config('database.connections.sqlite.database');
    }

    /**
     * Cache entries, sessions and queued jobs all describe the database that
     * the restore just discarded. Serving them afterwards mixes two datasets.
     */
    private function flushReplacedState(): void
    {
        try {
            Cache::flush();
        } catch (Throwable) {
            // A missing cache store must not fail the restore.
        }

        if (config('session.driver') === 'database') {
            $this->truncate(
                config('session.connection') ?: config('database.default'),
                config('session.table', 'sessions')
            );
        }

        if (config('queue.default') === 'database') {
            $this->truncate(
                config('queue.connections.database.connection') ?: config('database.default'),
                'jobs'
            );
        }
    }

    private function truncate(string $connection, string $table): void
    {
        try {
            if (Schema::connection($connection)->hasTable($table)) {
                DB::connection($connection)->table($table)->delete();
            }
        } catch (Throwable) {
            // Shard unavailable; the restore itself already succeeded.
        }
    }

    /**
     * The backups table lives in the database that was just replaced, so a
     * restore rewinds it and hides every newer backup file - including the
     * pre-restore snapshot. Re-index whatever is actually on disk.
     */
    private function reconcileIndex(): void
    {
        $path = config('backup.path');

        if (! File::isDirectory($path)) {
            return;
        }

        $known = Backup::pluck('filename')->all();

        foreach (File::files($path) as $file) {
            if ($file->getExtension() !== 'sqlite' || in_array($file->getFilename(), $known, true)) {
                continue;
            }

            $recovered = Backup::create([
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'note' => 'Recovered after restore',
            ]);

            // created_at is guarded, so date the row from the file itself
            // after creating it, keeping the list in chronological order.
            $recovered->created_at = Carbon::createFromTimestamp($file->getMTime());
            $recovered->save();
        }
    }
}
