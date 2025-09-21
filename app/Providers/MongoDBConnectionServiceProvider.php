<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MongoDB\Laravel\Connection;

class MongoDBConnectionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the MongoDB connection resolver
        $this->app['db']->extend('mongodb', function ($config, $name) {
            $config['name'] = $name;
            return new Connection($config);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}