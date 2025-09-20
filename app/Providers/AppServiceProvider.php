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
        // Bind TaskRepositoryInterface to TaskRepository implementation
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
        
        // Bind LogRepositoryInterface to LogRepository implementation
        $this->app->bind(LogRepositoryInterface::class, LogRepository::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}