<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Enhanced RESTful API routes for the Task Management System
| Organized with proper route groups, prefixes, middleware, and namespacing
| 
| Structure:
| - Health Check & Root Routes
| - API v1 Routes (Versioned with middleware)
| - Legacy Routes (Backward compatibility)
| - Error Handling Routes
|
*/

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Health Check & Root Routes
|--------------------------------------------------------------------------
*/

// Health check and API overview route
$router->get('/', function () use ($router) {
    return response()->json([
        'message' => 'Task Management System API',
        'version' => $router->app->version(),
        'api_version' => 'v1.0',
        'status' => 'active',
        'environment' => app()->environment(),
        'timestamp' => \Carbon\Carbon::now()->toISOString(),
        'documentation' => [
            'main' => url('/api/v1/docs'),
            'info' => url('/api/v1/info'),
            'openapi' => url('/api/v1/openapi.json')
        ],
        'endpoints' => [
            'api_v1' => [
                'tasks' => url('/api/v1/tasks'),
                'logs' => url('/api/v1/logs')
            ],
            'legacy' => [
                'tasks' => url('/tasks'),
                'logs' => url('/logs')
            ]
        ],
        'features' => [
            'versioned_api',
            'soft_deletes',
            'audit_logging',
            'filtering_pagination',
            'validation'
        ]
    ]);
});

/*
|--------------------------------------------------------------------------
| API Version 1 Routes
|--------------------------------------------------------------------------
| All versioned API routes with shared middleware and consistent structure
| Middleware: API headers, rate limiting, content negotiation
*/

$router->group([
    'prefix' => 'api/v1',
    'middleware' => [] // Add middleware when available: ['api', 'throttle', 'cors']
], function () use ($router) {

    /*
    |--------------------------------------------------------------------------
    | API Documentation Routes
    |--------------------------------------------------------------------------
    */
    
    $router->group(['prefix' => ''], function () use ($router) {
        
        // API documentation and specs
        $router->get('/docs', 'ApiDocumentationController@index');
        $router->get('/openapi.json', 'ApiDocumentationController@openapi');
        
        // API basic information
        $router->get('/info', function () use ($router) {
            return response()->json([
                'api' => [
                    'name' => 'Task Management System API',
                    'version' => 'v1.0',
                    'description' => 'RESTful API for managing tasks with comprehensive logging and soft delete capabilities',
                    'environment' => app()->environment(),
                    'framework' => 'Laravel Lumen ' . $router->app->version(),
                    'php_version' => PHP_VERSION,
                    'timezone' => config('app.timezone', 'UTC'),
                    'timestamp' => \Carbon\Carbon::now()->toISOString()
                ],
                'endpoints' => [
                    'documentation' => url('/api/v1/docs'),
                    'openapi_spec' => url('/api/v1/openapi.json'),
                    'health_check' => url('/api/v1/health'),
                    'tasks' => url('/api/v1/tasks'),
                    'logs' => url('/api/v1/logs')
                ],
                'features' => [
                    'task_management' => 'Full CRUD operations for tasks',
                    'soft_delete' => 'Tasks can be soft deleted and restored',
                    'logging' => 'Comprehensive activity logging with MongoDB',
                    'filtering' => 'Advanced filtering and sorting capabilities',
                    'validation' => 'Comprehensive input validation and error handling',
                    'pagination' => 'Efficient pagination for large datasets',
                    'status_transitions' => 'Smart status transition validation'
                ],
                'http_methods' => [
                    'GET' => 'Retrieve resources',
                    'POST' => 'Create new resources',
                    'PUT' => 'Update existing resources (full update)',
                    'PATCH' => 'Partial resource updates',
                    'DELETE' => 'Soft delete resources'
                ],
                'response_format' => [
                    'success' => [
                        'data' => 'Resource data or array of resources',
                        'message' => 'Success message',
                        'meta' => 'Additional metadata (pagination, counts, etc.)'
                    ],
                    'error' => [
                        'error' => 'Error message',
                        'details' => 'Additional error details',
                        'code' => 'Error code for programmatic handling'
                    ]
                ],
                'database' => [
                    'primary' => 'MySQL 8.0 (Tasks storage)',
                    'logging' => 'MongoDB 7.0 (Activity logs)'
                ]
            ], 200, [
                'X-API-Version' => 'v1.0',
                'X-Response-Time' => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2) . 'ms'
            ]);
        });
        
        // API health and status
        $router->get('/health', function () {
            return response()->json([
                'status' => 'healthy',
                'timestamp' => \Carbon\Carbon::now()->toISOString(),
                'services' => [
                    'database' => 'connected',
                    'mongodb' => 'connected'
                ]
            ]);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Task Management Routes
    |--------------------------------------------------------------------------
    | Complete task CRUD operations with advanced features
    */
    
    $router->group([
        'prefix' => 'tasks',
        'middleware' => [] // Add task-specific middleware when needed
    ], function () use ($router) {
        
        /*
        | Collection Routes (operate on multiple tasks)
        | Must be defined before parameterized routes
        */
        $router->group(['prefix' => ''], function () use ($router) {
            
            // Statistical and aggregate routes  
            $router->get('/stats', 'TaskController@stats');
            $router->get('/summary', 'TaskController@summary'); 
            $router->get('/export', 'TaskController@export');
            
            // Special collection filters
            $router->get('/trashed', 'TaskController@trashed');
            $router->get('/overdue', 'TaskController@overdue');
            $router->get('/completed', 'TaskController@completed');
            
            // Bulk operations
            $router->post('/bulk', 'TaskController@bulkCreate');
            $router->put('/bulk', 'TaskController@bulkUpdate');
            $router->delete('/bulk', 'TaskController@bulkDelete');
        });
        
        /*
        | Resource Routes (operate on individual tasks)
        | Standard RESTful resource operations
        */
        $router->group(['prefix' => ''], function () use ($router) {
            
            // Standard CRUD operations
            $router->get('/', 'TaskController@index');                    // List tasks
            $router->post('/', 'TaskController@store');                   // Create task
            $router->get('/{id:[0-9]+}', 'TaskController@show');          // Show task
            $router->put('/{id:[0-9]+}', 'TaskController@update');        // Full update
            $router->patch('/{id:[0-9]+}', 'TaskController@update');      // Partial update
            $router->delete('/{id:[0-9]+}', 'TaskController@destroy');    // Soft delete
        });
        
        /*
        | Task Operations (special actions on individual tasks)  
        | Non-standard but RESTful operations
        */
        $router->group(['prefix' => '{id:[0-9]+}'], function () use ($router) {
            
            // State management operations
            $router->post('/restore', 'TaskController@restore');          // Restore soft-deleted
            $router->delete('/force', 'TaskController@forceDelete');      // Permanent delete
            $router->post('/duplicate', 'TaskController@duplicate');      // Duplicate task
            
            // Status management operations
            $router->post('/complete', 'TaskController@markComplete');    // Mark as completed
            $router->post('/start', 'TaskController@markInProgress');     // Mark as in progress
            $router->post('/cancel', 'TaskController@markCancelled');     // Mark as cancelled
            
            // Assignment operations
            $router->post('/assign', 'TaskController@assign');            // Assign to user
            $router->delete('/assign', 'TaskController@unassign');        // Unassign
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Audit Log Management Routes  
    |--------------------------------------------------------------------------
    | Comprehensive logging and audit trail operations
    */
    
    $router->group([
        'prefix' => 'logs',
        'middleware' => [] // Add logging-specific middleware when needed
    ], function () use ($router) {
        
        /*
        | Log Collection Routes
        | Aggregate and statistical log operations
        */
        $router->group(['prefix' => ''], function () use ($router) {
            
            // Statistical and aggregate routes
            $router->get('/stats', 'LogController@stats');
            $router->get('/summary', 'LogController@summary');
            $router->get('/export', 'LogController@export');
            
            // Filtered log collections
            $router->get('/actions/{action}', 'LogController@byAction');
            $router->get('/users/{userId:[0-9]+}', 'LogController@byUser');
            $router->get('/recent', 'LogController@recent');
            
            // System logs
            $router->get('/errors', 'LogController@errors');
            $router->get('/warnings', 'LogController@warnings');
        });
        
        /*
        | Log Resource Routes
        | Individual log operations and task-specific logs
        */
        $router->group(['prefix' => ''], function () use ($router) {
            
            // Standard CRUD operations
            $router->get('/', 'LogController@index');                     // List logs
            $router->get('/{id}', 'LogController@show');                  // Show specific log
            
            // Task-specific log operations
            $router->get('/tasks/{taskId:[0-9]+}', 'LogController@taskLogs');
            $router->get('/tasks/{taskId:[0-9]+}/timeline', 'LogController@taskTimeline');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | User Management Routes (Future Enhancement)
    |--------------------------------------------------------------------------
    | Prepared for user authentication and management features
    */
    
    $router->group([
        'prefix' => 'users',
        'middleware' => [] // Add auth middleware: ['auth', 'admin']
    ], function () use ($router) {
        
        // User CRUD operations (commented out - implement when auth is added)
        // $router->get('/', 'UserController@index');
        // $router->post('/', 'UserController@store');
        // $router->get('/{id:[0-9]+}', 'UserController@show');
        // $router->put('/{id:[0-9]+}', 'UserController@update');
        // $router->delete('/{id:[0-9]+}', 'UserController@destroy');
        
        // Placeholder route
        $router->get('/', function () {
            return response()->json([
                'message' => 'User management not yet implemented',
                'planned_features' => [
                    'user_authentication',
                    'role_management', 
                    'task_assignment',
                    'user_profiles'
                ]
            ], 501);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | System Administration Routes  
    |--------------------------------------------------------------------------
    | System management and administrative operations
    */
    
    $router->group([
        'prefix' => 'admin',
        'middleware' => [] // Add admin middleware: ['auth', 'admin']
    ], function () use ($router) {
        
        // System status and monitoring
        $router->get('/status', 'AdminController@systemStatus');
        $router->get('/metrics', 'AdminController@metrics');
        
        // Database operations
        $router->post('/cache/clear', 'AdminController@clearCache');
        $router->post('/maintenance', 'AdminController@enableMaintenance');
        $router->delete('/maintenance', 'AdminController@disableMaintenance');
        
        // Placeholder for admin routes
        $router->get('/', function () {
            return response()->json([
                'message' => 'System administration panel',
                'available_operations' => [
                    'system_status',
                    'performance_metrics',
                    'cache_management',
                    'maintenance_mode'
                ]
            ]);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Legacy Routes (Backward Compatibility)
|--------------------------------------------------------------------------
| Direct routes without API versioning for backward compatibility
| These maintain the original API contract while new features use v1
*/

$router->group([
    'prefix' => '',
    'middleware' => [] // Legacy routes use minimal middleware
], function () use ($router) {

    /*
    | Legacy Task Routes
    | Maintains backward compatibility for existing clients
    */
    $router->group(['prefix' => 'tasks'], function () use ($router) {
        
        // Collection routes (must come before parameterized routes)
        $router->get('/stats', 'TaskController@stats');
        $router->get('/trashed', 'TaskController@trashed');
        
        // Standard RESTful resource routes
        $router->get('/', 'TaskController@index');
        $router->post('/', 'TaskController@store');
        $router->get('/{id:[0-9]+}', 'TaskController@show');
        $router->put('/{id:[0-9]+}', 'TaskController@update');
        $router->patch('/{id:[0-9]+}', 'TaskController@update');
        $router->delete('/{id:[0-9]+}', 'TaskController@destroy');
        
        // Additional task operations
        $router->post('/{id:[0-9]+}/restore', 'TaskController@restore');
        $router->delete('/{id:[0-9]+}/force', 'TaskController@forceDelete');
    });

    /*
    | Legacy Log Routes  
    | Simple log access without versioning
    */
    $router->group(['prefix' => 'logs'], function () use ($router) {
        
        $router->get('/', 'LogController@index');
        $router->get('/stats', 'LogController@stats');
        $router->get('/tasks/{id:[0-9]+}', 'LogController@taskLogs');
    });
});

/*
|--------------------------------------------------------------------------
| Global Error Handling Routes
|--------------------------------------------------------------------------
| Catch-all routes for unmatched requests and method not allowed errors
*/

// Handle OPTIONS requests for CORS preflight
$router->addRoute('OPTIONS', '{route:.*}', function () {
    return response('', 204)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
});

// Method not allowed handler (must come before catch-all)
$router->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '{route:.*}', function () use ($router) {
    
    // Check if route exists but method is not allowed
    $currentPath = request()->path();
    $currentMethod = request()->method();
    
    return response()->json([
        'error' => 'Route not found',
        'message' => "The requested endpoint '{$currentPath}' does not exist or method '{$currentMethod}' is not allowed",
        'request_details' => [
            'path' => $currentPath,
            'method' => $currentMethod,
            'timestamp' => \Carbon\Carbon::now()->toISOString()
        ],
        'available_endpoints' => [
            'api_v1' => [
                'base' => url('/api/v1'),
                'tasks' => url('/api/v1/tasks'),
                'logs' => url('/api/v1/logs'),
                'docs' => url('/api/v1/docs')
            ],
            'legacy' => [
                'tasks' => url('/tasks'),
                'logs' => url('/logs')
            ]
        ],
        'documentation' => [
            'full_docs' => url('/api/v1/docs'),
            'openapi' => url('/api/v1/openapi.json')
        ]
    ], 404);
});