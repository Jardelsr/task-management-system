<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Log\LogManager;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class LoggingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('log', function () {
            $logManager = new LogManager($this->app);

            // Configure daily rotating logs
            $this->configureDailyLogs($logManager);
            
            // Configure specialized log channels
            $this->configureSpecializedChannels($logManager);

            return $logManager;
        });
    }

    /**
     * Configure daily rotating logs
     */
    private function configureDailyLogs(LogManager $logManager): void
    {
        // Main application log
        $logManager->extend('daily', function () {
            $handler = new RotatingFileHandler(
                storage_path('logs/lumen.log'),
                14, // Keep 14 days
                config('logging.channels.daily.level', 'debug')
            );
            $handler->setFormatter(new LineFormatter(null, null, true, true));
            
            return $handler;
        });

        // Error log
        $logManager->extend('error_file', function () {
            $handler = new RotatingFileHandler(
                storage_path('logs/error.log'),
                30, // Keep 30 days
                'error'
            );
            $handler->setFormatter(new LineFormatter(null, null, true, true));
            
            return $handler;
        });
    }

    /**
     * Configure specialized log channels
     */
    private function configureSpecializedChannels(LogManager $logManager): void
    {
        // Task operations log
        $logManager->extend('task_operations', function () {
            $handler = new RotatingFileHandler(
                storage_path('logs/task_operations.log'),
                30,
                'info'
            );
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] %level_name%: %message% %context%\n",
                'Y-m-d H:i:s',
                true,
                true
            ));
            
            return $handler;
        });

        // Validation errors log
        $logManager->extend('validation_errors', function () {
            $handler = new RotatingFileHandler(
                storage_path('logs/validation_errors.log'),
                30,
                'warning'
            );
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] VALIDATION: %message% %context%\n",
                'Y-m-d H:i:s',
                true,
                true
            ));
            
            return $handler;
        });

        // Database errors log
        $logManager->extend('database_errors', function () {
            $handler = new RotatingFileHandler(
                storage_path('logs/database_errors.log'),
                30,
                'error'
            );
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] DATABASE: %message% %context%\n",
                'Y-m-d H:i:s',
                true,
                true
            ));
            
            return $handler;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure default log channel
        if ($this->app->bound('log')) {
            $this->app['log']->setDefaultDriver(config('logging.default', 'daily'));
        }
    }
}