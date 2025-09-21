<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated functionality that is being used. This allows you
    | to get your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Lumen uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'error_file'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/lumen.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lumen.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'error_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/error.log'),
            'level' => 'error',
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'task_operations' => [
            'driver' => 'daily',
            'path' => storage_path('logs/task_operations.log'),
            'level' => 'info',
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'validation_errors' => [
            'driver' => 'daily',
            'path' => storage_path('logs/validation_errors.log'),
            'level' => 'warning',
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'database_errors' => [
            'driver' => 'daily',
            'path' => storage_path('logs/database_errors.log'),
            'level' => 'error',
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/lumen.log'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for specific error logging behavior
    |
    */
    'error_logging' => [
        'include_stack_trace' => env('LOG_INCLUDE_STACK_TRACE', true),
        'include_request_data' => env('LOG_INCLUDE_REQUEST_DATA', true),
        'max_context_size' => env('LOG_MAX_CONTEXT_SIZE', 1000),
        'sensitive_fields' => ['password', 'token', 'api_key', 'secret'],
    ],
];