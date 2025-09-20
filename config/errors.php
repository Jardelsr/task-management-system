<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Error Response Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for error responses and exception handling
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Error Messages
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'task_not_found' => 'The requested task could not be found',
        'validation_failed' => 'The provided data is invalid',
        'database_error' => 'A database error occurred',
        'unauthorized' => 'Authentication required',
        'forbidden' => 'Access denied',
        'server_error' => 'An unexpected error occurred',
        'route_not_found' => 'The requested endpoint does not exist',
        'method_not_allowed' => 'The HTTP method is not allowed for this endpoint',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Codes
    |--------------------------------------------------------------------------
    */
    'codes' => [
        'TASK_NOT_FOUND' => 'TASK_NOT_FOUND',
        'VALIDATION_FAILED' => 'VALIDATION_FAILED',
        'DATABASE_ERROR' => 'DATABASE_ERROR',
        'TASK_OPERATION_FAILED' => 'TASK_OPERATION_FAILED',
        'LOGGING_FAILED' => 'LOGGING_FAILED',
        'UNAUTHORIZED' => 'UNAUTHORIZED',
        'FORBIDDEN' => 'FORBIDDEN',
        'INTERNAL_ERROR' => 'INTERNAL_ERROR',
        'ROUTE_NOT_FOUND' => 'ROUTE_NOT_FOUND',
        'METHOD_NOT_ALLOWED' => 'METHOD_NOT_ALLOWED',
        'HTTP_ERROR' => 'HTTP_ERROR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        // Log all exceptions by default
        'log_exceptions' => true,
        
        // Log validation errors (useful for debugging)
        'log_validation_errors' => env('LOG_VALIDATION_ERRORS', false),
        
        // Include stack trace in logs for debugging
        'include_stack_trace' => env('APP_DEBUG', false),
        
        // Log to specific channels for different error types
        'channels' => [
            'database' => 'database-errors',
            'validation' => 'validation-errors',
            'task_operations' => 'task-operations',
            'logging' => 'logging-errors',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    */
    'debug' => [
        // Show detailed error information in debug mode
        'show_details' => env('APP_DEBUG', false),
        
        // Include file and line information in responses
        'include_trace' => env('APP_DEBUG', false),
        
        // Show SQL queries in database errors (debug mode only)
        'show_sql' => env('APP_DEBUG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting for Error Responses
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        // Enable rate limiting for error responses to prevent abuse
        'enabled' => env('ERROR_RATE_LIMITING', true),
        
        // Maximum error responses per minute
        'max_per_minute' => 60,
        
        // Rate limit key format
        'key_format' => 'error_limit:{ip}',
    ],
];