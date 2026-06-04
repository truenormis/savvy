<?php

namespace App\Database;

/**
 * Ensures the dedicated SQLite shard files (queue/cache/sessions) exist on disk
 * so connecting to them never throws "database file does not exist". Pure
 * filesystem concern — kept out of the service providers and container-resolvable.
 */
class SqliteShardProvisioner
{
    /**
     * @param  array<int, string>  $connections  Names of the shard connections to provision.
     */
    public function __construct(
        private array $connections = ['sqlite_queue', 'sqlite_cache', 'sqlite_sessions'],
    ) {}

    public function ensureFilesExist(): void
    {
        foreach ($this->connections as $name) {
            $config = config("database.connections.{$name}");

            if (! is_array($config) || ($config['driver'] ?? null) !== 'sqlite') {
                continue;
            }

            $path = $config['database'] ?? null;

            if (! $this->isProvisionableFile($path)) {
                continue;
            }

            if (! file_exists($path) && is_dir(dirname($path))) {
                touch($path);
            }
        }
    }

    private function isProvisionableFile(mixed $path): bool
    {
        return is_string($path)
            && $path !== ''
            && $path !== ':memory:'
            && ! str_contains($path, '?');
    }
}
