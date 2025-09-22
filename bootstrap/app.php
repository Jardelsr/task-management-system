<?php

require_once __DIR__.'/../vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
*/

$app = new Laravel\Lumen\Application(
    dirname(__DIR__)
);

$app->withFacades();
$app->withEloquent();

// Enable views
$app->instance('path.config', app()->basePath() . DIRECTORY_SEPARATOR . 'config');
$app->instance('path.storage', app()->basePath() . DIRECTORY_SEPARATOR . 'storage');
$app->configure('view');

// Enable cache functionality for rate limiting
$app->register(Illuminate\Cache\CacheServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

/*
|--------------------------------------------------------------------------
| Register Config Files
|--------------------------------------------------------------------------
*/

$app->configure('database');
$app->configure('mongo');
$app->configure('errors');
$app->configure('api');
$app->configure('validation_messages');
$app->configure('log_responses');
$app->configure('logging');
$app->configure('cache');

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
*/

// Register the logging middleware and SQL injection protection
$app->middleware([
    App\Http\Middleware\RequestResponseLoggingMiddleware::class,
    App\Http\Middleware\SqlInjectionProtectionMiddleware::class,
    App\Http\Middleware\SecurityValidationMiddleware::class
]);

// Register route middleware
$app->routeMiddleware([
    'throttle' => App\Http\Middleware\RateLimitingMiddleware::class,
]);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
*/

// Register services in the correct order for Lumen 11
$app->register(Illuminate\View\ViewServiceProvider::class);
$app->register(MongoDB\Laravel\MongoDBServiceProvider::class);
$app->register(App\Providers\MongoDBConnectionServiceProvider::class);
$app->register(App\Providers\LoggingServiceProvider::class);
$app->register(App\Providers\ValidationServiceProvider::class);
$app->register(App\Providers\SqlInjectionProtectionServiceProvider::class);
$app->register(App\Providers\AppServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
*/

$app->router->group([
    'namespace' => 'App\Http\Controllers',
], function ($router) {
    require __DIR__.'/../routes/web.php';
});

return $app;