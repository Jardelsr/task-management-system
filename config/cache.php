<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache "store" that will be used when
    | using the Caching library. This connection is used when another is
    | not explicitly specified.
    |
    */

    'default' => env('CACHE_DRIVER', 'array'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "apc", "array", "database", "file",
    |            "memcached", "redis", "dynamodb", "octane", "null"
    |
    */

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing a RAM based store such as APC or Memcached, there might
    | be other applications utilizing the same cache. So, we'll specify a
    | value to get prefixed to all our keys so we can avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', 'task_mgmt_cache'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the custom rate limiting middleware
    |
    */

    'rate_limiting' => [
        'default' => [
            'requests' => 60,      // Number of requests
            'per_minutes' => 1,    // Per time window
        ],
        'api' => [
            'requests' => 100,     // API endpoints can handle more
            'per_minutes' => 1,
        ],
        'strict' => [
            'requests' => 30,      // Stricter limits for sensitive endpoints
            'per_minutes' => 1,
        ],
        'burst' => [
            'requests' => 200,     // Allow burst traffic
            'per_minutes' => 5,    // Over 5 minutes
        ],
        'test' => [
            'requests' => 5,       // Very low limit for testing
            'per_minutes' => 1,
        ]
    ]
];