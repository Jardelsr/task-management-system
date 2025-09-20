<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\TaskRepositoryInterface;
use App\Repositories\TaskRepository;
use App\Repositories\LogRepositoryInterface;
use App\Repositories\LogRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Repository Pattern Bindings
        $this->registerRepositories();
        
        // Environment-specific service bindings
        $this->registerEnvironmentServices();
    }

    /**
     * Register repository interface bindings
     *
     * @return void
     */
    private function registerRepositories(): void
    {
        // Bind TaskRepositoryInterface to TaskRepository implementation
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
        
        // Bind LogRepositoryInterface to LogRepository implementation
        $this->app->bind(LogRepositoryInterface::class, LogRepository::class);
    }

    /**
     * Register environment-specific services
     *
     * @return void
     */
    private function registerEnvironmentServices(): void
    {
        // Debug-specific bindings for local environment
        if ($this->app->environment('local', 'testing')) {
            // Add debug-specific services here if needed
        }

        // Production-specific optimizations
        if ($this->app->environment('production')) {
            // Add production-specific services here if needed
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Bootstrap MongoDB indexes if needed
        $this->setupMongoDBIndexes();
        
        // Setup application-wide configurations
        $this->setupApplicationConfigurations();
    }

    /**
     * Setup MongoDB indexes for better performance
     *
     * @return void
     */
    private function setupMongoDBIndexes(): void
    {
        // MongoDB indexes will be created automatically based on config/mongo.php
        // This method can be extended for custom index creation if needed
    }

    /**
     * Setup application-wide configurations
     *
     * @return void
     */
    private function setupApplicationConfigurations(): void
    {
        // Set default timezone
        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));
        }
        
        // Additional application configurations can be added here
    }
}