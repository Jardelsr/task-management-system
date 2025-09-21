<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MongoDB\Laravel\MongoDBServiceProvider as LaravelMongoDBServiceProvider;

class MongoDBServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Nothing needed here for Lumen
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // The MongoDB service provider should handle the connection registration
        // No custom logic needed here as it's handled by the official package
    }
}