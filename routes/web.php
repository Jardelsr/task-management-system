<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

/** @var \Laravel\Lumen\Routing\Router $router */

// Health check route
$router->get('/', function () use ($router) {
    return response()->json([
        'message' => 'Task Management System API',
        'version' => $router->app->version(),
        'status' => 'active',
        'timestamp' => now()->toISOString()
    ]);
});

// API version prefix
$router->group(['prefix' => 'api/v1'], function () use ($router) {

    // Task management routes
    $router->group(['prefix' => 'tasks'], function () use ($router) {
        
        // GET /api/v1/tasks - List all tasks (with optional status filter)
        $router->get('/', 'TaskController@index');
        
        // GET /api/v1/tasks/stats - Get task statistics
        $router->get('/stats', 'TaskController@stats');
        
        // GET /api/v1/tasks/{id} - Show specific task
        $router->get('/{id:[0-9]+}', 'TaskController@show');
        
        // POST /api/v1/tasks - Create new task
        $router->post('/', 'TaskController@store');
        
        // PUT /api/v1/tasks/{id} - Update specific task
        $router->put('/{id:[0-9]+}', 'TaskController@update');
        
        // PATCH /api/v1/tasks/{id} - Partially update specific task
        $router->patch('/{id:[0-9]+}', 'TaskController@update');
        
        // DELETE /api/v1/tasks/{id} - Delete specific task
        $router->delete('/{id:[0-9]+}', 'TaskController@destroy');
    });

    // Task logs routes
    $router->group(['prefix' => 'logs'], function () use ($router) {
        
        // GET /api/v1/logs?id=:id - List last 30 logs or specific log by id
        $router->get('/', 'LogController@index');
        
        // GET /api/v1/logs/tasks/{id} - Get logs for specific task
        $router->get('/tasks/{id:[0-9]+}', 'LogController@taskLogs');
        
        // GET /api/v1/logs/stats - Get log statistics
        $router->get('/stats', 'LogController@stats');
    });

    // API Documentation route (future implementation)
    $router->get('/docs', function () {
        return response()->json([
            'message' => 'API Documentation',
            'swagger_url' => '/api/docs.json',
            'endpoints' => [
                'GET /api/v1/tasks' => 'List all tasks',
                'GET /api/v1/tasks/{id}' => 'Show specific task',
                'POST /api/v1/tasks' => 'Create new task',
                'PUT /api/v1/tasks/{id}' => 'Update task',
                'DELETE /api/v1/tasks/{id}' => 'Delete task',
                'GET /api/v1/tasks/stats' => 'Task statistics',
                'GET /api/v1/logs' => 'Recent logs',
                'GET /api/v1/logs/tasks/{id}' => 'Task specific logs'
            ]
        ]);
    });
});

// Direct routes as per requirements (without /api/v1 prefix)
$router->group(['prefix' => 'tasks'], function () use ($router) {
    // POST /tasks - Create new task
    $router->post('/', 'TaskController@store');
    
    // GET /tasks - List all tasks (with advanced filtering support)
    $router->get('/', 'TaskController@index');
    
    // GET /tasks/simple - Simple task listing (backward compatibility)
    $router->get('/simple', 'TaskController@simpleListing');
    
    // GET /tasks/{id} - Show specific task  
    $router->get('/{id:[0-9]+}', 'TaskController@show');
    
    // PUT /tasks/{id} - Update specific task
    $router->put('/{id:[0-9]+}', 'TaskController@update');
    
    // DELETE /tasks/{id} - Delete specific task
    $router->delete('/{id:[0-9]+}', 'TaskController@destroy');
});

// Root level logs route as per requirements
// GET /logs?id=:id - List last 30 logs or specific log by id
$router->get('/logs', 'LogController@rootLogs');

// Catch-all route for 404 responses
$router->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '{route:.*}', function () {
    return response()->json([
        'error' => 'Route not found',
        'message' => 'The requested endpoint does not exist'
    ], 404);
});