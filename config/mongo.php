<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default MongoDB Connection
    |--------------------------------------------------------------------------
    |
    | Used for the TaskLog model and logging system
    |
    */

    'default' => env('MONGO_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | MongoDB Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [
        'default' => [
            'driver' => 'mongodb',
            'dsn' => env('MONGO_URI'),
            'host' => env('MONGO_HOST', 'mongodb'),
            'port' => env('MONGO_PORT', 27017),
            'database' => env('MONGO_DATABASE', 'task_logs'),
            'username' => env('MONGO_USERNAME', ''),
            'password' => env('MONGO_PASSWORD', ''),
            'options' => [
                'appname' => 'Task Management System',
                'readPreference' => 'primary',
                'retryWrites' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MongoDB Collections
    |--------------------------------------------------------------------------
    */
    
    'collections' => [
        'task_logs' => [
            'name' => 'task_logs',
            'indexes' => [
                ['key' => ['created_at' => -1]],
                ['key' => ['task_id' => 1]],
                ['key' => ['action' => 1]],
            ],
        ],
    ],
];