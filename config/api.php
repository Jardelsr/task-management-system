<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API responses, versioning, and metadata
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    */
    'version' => env('API_VERSION', '1.0'),
    
    /*
    |--------------------------------------------------------------------------
    | Response Configuration
    |--------------------------------------------------------------------------
    */
    'responses' => [
        'include_execution_time' => env('API_INCLUDE_EXECUTION_TIME', true),
        'include_request_id' => env('API_INCLUDE_REQUEST_ID', true),
        'include_debug_info' => env('API_INCLUDE_DEBUG_INFO', false),
        'max_per_page' => env('API_MAX_PER_PAGE', 1000),
        'default_per_page' => env('API_DEFAULT_PER_PAGE', 50),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Headers Configuration
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'version_header' => 'X-API-Version',
        'request_id_header' => 'X-Request-ID',
        'execution_time_header' => 'X-Execution-Time',
        'total_count_header' => 'X-Total-Count',
    ],
];