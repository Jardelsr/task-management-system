<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SqlInjectionProtectionService;

class SqlInjectionProtectionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(SqlInjectionProtectionService::class, function ($app) {
            return new SqlInjectionProtectionService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Any bootstrapping logic can go here
    }
}