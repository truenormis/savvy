<?php

namespace App\Services\Health\Checks;

use App\Services\Health\HealthCheck;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

class MigrationsCheck implements HealthCheck
{
    public function __construct(private readonly Migrator $migrator) {}

    public function run(): array
    {
        try {
            $pending = $this->pendingCount();
        } catch (Throwable $e) {
            return [
                'component' => 'schema',
                'measurement' => 'migrations',
                'type' => 'component',
                'ok' => false,
                'output' => $e->getMessage(),
            ];
        }

        return [
            'component' => 'schema',
            'measurement' => 'migrations',
            'type' => 'component',
            'ok' => $pending === 0,
            'observedValue' => max($pending, 0),
            'observedUnit' => 'pending',
        ];
    }

    private function pendingCount(): int
    {
        $repository = $this->migrator->getRepository();

        if (! $repository->repositoryExists()) {
            return -1;
        }

        return count(array_diff(
            array_keys($this->migrator->getMigrationFiles(database_path('migrations'))),
            $repository->getRan(),
        ));
    }
}
