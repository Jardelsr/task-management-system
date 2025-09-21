<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\DatabaseManager;
use MongoDB\Laravel\Connection;

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
        // Register MongoDB connection resolver in the database manager
        $db = $this->app->make('db');
        $db->extend('mongodb', function ($config, $name) {
            $config['name'] = $name;
            return new \MongoDB\Laravel\Connection($config);
        });
    }
}