<?php

namespace App\Providers;

use App\Services\TwoFactorService;
use Illuminate\Support\ServiceProvider;

class TwoFactorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TwoFactorService::class, function () {
            return new TwoFactorService();
        });
    }

    public function boot(): void
    {
        //
    }
}
