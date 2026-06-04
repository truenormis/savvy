<?php

namespace App\Providers;

use App\Database\SqliteShardProvisioner;
use Illuminate\Support\ServiceProvider;

class DatabaseShardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(SqliteShardProvisioner::class)->ensureFilesExist();
    }
}
