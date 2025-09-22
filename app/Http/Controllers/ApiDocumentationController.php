<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use OpenApi\Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * API Documentation Controller
 * 
 * Provides comprehensive API documentation, OpenAPI specifications,
 * and interactive documentation endpoints with robust error handling.
 * 
 * @OA\Info(
 *     title="Task Management System API",
 *     version="1.0.0",
 *     description="A comprehensive RESTful API for managing tasks with soft delete capabilities, comprehensive logging, and advanced filtering features. Built with Lumen framework for high performance and scalability.",
 *     @OA\License(
 *         name="MIT License",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api/v1",
 *     description="Local Development Server"
 * )
 * 
 * 
 * @OA\Tag(
 *     name="Tasks",
 *     description="Task management operations - CRUD operations, filtering, soft deletes, and restoration"
 * )
 * 
 * @OA\Tag(
 *     name="Logs",
 *     description="System audit logs and activity tracking"
 * )
 * 
 * @OA\Tag(
 *     name="System",
 *     description="System health checks and API information"
 * )
 * 
 * @OA\ExternalDocumentation(
 *     description="API Documentation Wiki"
 * )
 */
class ApiDocumentationController extends Controller
{
    /**
     * Display comprehensive API documentation
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Rate limiting for documentation requests
            $this->checkRateLimit(
                'documentation:' . request()->ip(),
                100, // 100 requests
                3600 // per hour
            );

            return $this->handleOperationWithTimeout(function () {
                return $this->successResponse([
                    'api' => [
                        'name' => 'Task Management System API',
                        'version' => 'v1.0',
                        'description' => 'Comprehensive task management with audit logging',
                        'base_url' => url('/api/v1'),
                        'last_updated' => Carbon::now()->toISOString()
                    ],
                    'authentication' => [
                        'type' => 'planned',
                        'methods' => ['bearer_token', 'api_key'],
                        'note' => 'Authentication system planned for future release'
                    ],
                    'resources' => [
                        'tasks' => [
                            'description' => 'Complete task lifecycle management',
                            'endpoints' => [
                                'collection' => [
                                    'GET /api/v1/tasks' => [
                                        'description' => 'List all tasks with filtering and pagination',
                                        'parameters' => ['status', 'assigned_to', 'created_by', 'overdue', 'sort', 'page', 'limit']
                                    ],
                                    'POST /api/v1/tasks' => [
                                        'description' => 'Create a new task',
                                        'required' => ['title'],
                                        'optional' => ['description', 'status', 'due_date', 'assigned_to']
                                    ],
                                    'GET /api/v1/tasks/stats' => [
                                        'description' => 'Get comprehensive task statistics',
                                        'returns' => 'counts by status, overdue tasks, completion rates'
                                    ],
                                    'GET /api/v1/tasks/trashed' => [
                                        'description' => 'List soft-deleted tasks',
                                        'note' => 'Supports restore operations'
                                    ],
                                    'POST /api/v1/tasks/bulk' => [
                                        'description' => 'Create multiple tasks in single request',
                                        'parameters' => 'Array of task objects'
                                    ]
                                ],
                                'resource' => [
                                    'GET /api/v1/tasks/{id}' => [
                                        'description' => 'Show a specific task with details',
                                        'includes' => 'creation/update timestamps, soft delete status'
                                    ],
                                    'PUT /api/v1/tasks/{id}' => [
                                        'description' => 'Full update of task (all fields)',
                                        'note' => 'Missing fields will be set to default values'
                                    ],
                                    'PATCH /api/v1/tasks/{id}' => [
                                        'description' => 'Partial update (only provided fields)',
                                        'note' => 'Preferred method for updates'
                                    ],
                                    'DELETE /api/v1/tasks/{id}' => [
                                        'description' => 'Soft delete a task (recoverable)',
                                        'note' => 'Task remains in database, can be restored'
                                    ]
                                ],
                                'operations' => [
                                    'POST /api/v1/tasks/{id}/restore' => [
                                        'description' => 'Restore a soft-deleted task',
                                        'status_code' => 200
                                    ],
                                    'DELETE /api/v1/tasks/{id}/force' => [
                                        'description' => 'Permanently delete a task (irreversible)',
                                        'status_code' => 204,
                                        'warning' => 'This action cannot be undone'
                                    ],
                                    'POST /api/v1/tasks/{id}/complete' => [
                                        'description' => 'Mark task as completed',
                                        'note' => 'Sets completion timestamp automatically'
                                    ],
                                    'POST /api/v1/tasks/{id}/assign' => [
                                        'description' => 'Assign task to a user',
                                        'parameters' => ['user_id']
                                    ]
                                ]
                            ]
                ],
                'logs' => [
                    'description' => 'Comprehensive audit trail and activity logs',
                    'storage' => 'MongoDB for scalable log storage',
                    'endpoints' => [
                        'collection' => [
                            'GET /api/v1/logs' => [
                                'description' => 'List recent logs with filtering or retrieve specific log by ID',
                                'parameters' => ['action', 'task_id', 'user_id', 'date_from', 'date_to', 'id'],
                                'note' => 'Use ?id=<log_id> to retrieve specific log, or omit for filtered list'
                            ],
                            'GET /api/v1/logs/stats' => [
                                'description' => 'Log statistics and activity metrics',
                                'returns' => 'action counts, user activity, timeline data'
                            ],
                            'GET /api/v1/logs/recent' => [
                                'description' => 'Most recent system activity (last 30 logs by default)',
                                'default_limit' => 30
                            ]
                        ],
                        'resource' => [
                            'GET /api/v1/logs/{id}' => [
                                'description' => 'Show specific log entry by MongoDB ObjectId',
                                'includes' => 'full context and metadata',
                                'id_format' => '24-character hexadecimal string (MongoDB ObjectId)',
                                'validation' => 'Validates ObjectId format and returns detailed error for invalid IDs'
                            ],
                            'GET /api/v1/logs/tasks/{task_id}' => [
                                'description' => 'Complete audit trail for a specific task',
                                'returns' => 'chronological task history'
                            ]
                        ]
                    ]
                ]
            ],
            'features' => [
                'versioning' => 'API versioning with backward compatibility',
                'filtering' => 'Advanced filtering on all list endpoints',
                'sorting' => 'Multi-field sorting with direction control', 
                'pagination' => 'Cursor and offset-based pagination',
                'soft_deletes' => 'Safe deletion with restore capabilities',
                'validation' => 'Comprehensive input validation with detailed errors',
                'logging' => 'Automatic audit logging for all operations',
                'bulk_operations' => 'Batch processing for efficiency',
                'status_management' => 'Advanced task status workflows'
            ],
            'response_format' => [
                'success' => [
                    'structure' => 'data, message, meta (pagination, timing, etc.)',
                    'status_codes' => [200, 201, 202, 204]
                ],
                'error' => [
                    'structure' => 'error, message, details, timestamp',
                    'status_codes' => [400, 404, 422, 500]
                ]
            ],
            'rate_limits' => [
                'general' => '1000 requests/hour',
                'bulk_operations' => '100 requests/hour',
                'note' => 'Planned feature - not yet implemented'
            ]
        ], 'API Documentation Retrieved Successfully');
            }, 'api_documentation', 15); // 15 second timeout

        } catch (\Exception $e) {
            return $this->handleWithFallback(
                function () use ($e) {
                    throw $e;
                },
                function () {
                    // Fallback to minimal documentation
                    return $this->successResponse([
                        'api' => [
                            'name' => 'Task Management System API',
                            'version' => 'v1.0',
                            'status' => 'partial_data_available'
                        ],
                        'message' => 'Full documentation temporarily unavailable'
                    ], 'Partial API Documentation Retrieved');
                },
                'api_documentation_fallback'
            );
        }
    }

    /**
     * Generate OpenAPI 3.0 specification
     *
     * @return JsonResponse
     */
    public function openapi(): JsonResponse
    {
        try {
            // Load the enhanced OpenAPI specification from file
            $specFile = base_path('openapi-enhanced.json');
            
            if (file_exists($specFile)) {
                $spec = json_decode(file_get_contents($specFile), true);
                
                if ($spec === null) {
                    throw new \Exception('Invalid JSON in OpenAPI specification file');
                }
                
                // Update server URLs to match current environment
                $currentUrl = request()->getSchemeAndHttpHost() . '/api/v1';
                $environment = app()->environment();
                
                // Update the first server (current environment) to match the actual request
                $spec['servers'][0]['url'] = $currentUrl;
                $spec['servers'][0]['description'] = ucfirst($environment) . ' Server (Current)';
                
                // Add environment-specific metadata
                $spec['info']['x-environment'] = $environment;
                $spec['info']['x-server-time'] = Carbon::now()->toISOString();
                $spec['info']['x-lumen-version'] = app()->version();
                
                return response()->json($spec, 200, [
                    'Content-Type' => 'application/json',
                    'X-API-Version' => 'v1.0',
                    'Cache-Control' => 'public, max-age=3600'
                ]);
            }
            
            // Fallback to basic spec if enhanced file doesn't exist
            $currentUrl = request()->getSchemeAndHttpHost() . '/api/v1';
            $environment = app()->environment();
            
            $spec = [
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Task Management System API',
                    'version' => '1.0.0',
                    'description' => 'A comprehensive RESTful API for managing tasks. Built with Lumen framework.',
                    'contact' => [
                        'name' => 'API Support Team',
                        'email' => 'api-support@taskmanagement.com'
                    ],
                    'license' => [
                        'name' => 'MIT License',
                        'url' => 'https://opensource.org/licenses/MIT'
                    ],
                    'x-environment' => $environment,
                    'x-server-time' => Carbon::now()->toISOString(),
                    'x-lumen-version' => app()->version()
                ],
                'servers' => [
                    [
                        'url' => $currentUrl,
                        'description' => ucfirst($environment) . ' Server (Current)'
                    ]
                ],
                'paths' => [
                    '/tasks' => [
                        'get' => [
                            'tags' => ['Tasks'],
                            'summary' => 'Get all tasks',
                            'responses' => [
                                '200' => [
                                    'description' => 'Tasks retrieved successfully'
                                ]
                            ]
                        ]
                    ]
                ],
                'components' => [
                    'schemas' => [
                        'Task' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'title' => ['type' => 'string'],
                                'status' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ];
            
            return response()->json($spec, 200, [
                'Content-Type' => 'application/json',
                'X-API-Version' => 'v1.0'
            ]);
            
        } catch (\Exception $e) {
            Log::error('OpenAPI generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to generate OpenAPI specification',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get API health status with comprehensive error checking
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            
            // Check database connectivity
            $dbStatus = $this->checkDatabaseHealth();
            
            // Check storage accessibility
            $storageStatus = $this->checkStorageHealth();
            
            // Check memory usage
            $memoryStatus = $this->checkMemoryHealth();
            
            $executionTime = microtime(true) - $startTime;
            
            $healthStatus = [
                'status' => 'healthy',
                'timestamp' => Carbon::now()->toISOString(),
                'version' => '1.0.0',
                'checks' => [
                    'database' => $dbStatus,
                    'storage' => $storageStatus,
                    'memory' => $memoryStatus
                ],
                'performance' => [
                    'response_time_ms' => round($executionTime * 1000, 2)
                ]
            ];
            
            // Determine overall status
            $allChecksHealthy = collect($healthStatus['checks'])->every(function ($check) {
                return $check['status'] === 'healthy';
            });
            
            if (!$allChecksHealthy) {
                $healthStatus['status'] = 'degraded';
            }
            
            $statusCode = $allChecksHealthy ? 200 : 503;
            
            return response()->json($healthStatus, $statusCode);
            
        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => Carbon::now()->toISOString(),
                'error' => 'Health check failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 503);
        }
    }

    /**
     * Check database health
     *
     * @return array
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $startTime = microtime(true);
            DB::connection()->getPdo();
            $responseTime = microtime(true) - $startTime;
            
            return [
                'status' => 'healthy',
                'response_time_ms' => round($responseTime * 1000, 2),
                'connection' => 'active'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
                'details' => config('app.debug') ? $e->getMessage() : 'Connection error'
            ];
        }
    }

    /**
     * Check storage health
     *
     * @return array
     */
    private function checkStorageHealth(): array
    {
        try {
            $storagePath = storage_path();
            $testFile = $storagePath . '/health_check_' . time() . '.tmp';
            
            // Test write
            file_put_contents($testFile, 'health_check');
            
            // Test read
            $content = file_get_contents($testFile);
            
            // Cleanup
            unlink($testFile);
            
            if ($content !== 'health_check') {
                throw new \Exception('File content mismatch');
            }
            
            return [
                'status' => 'healthy',
                'writable' => true,
                'readable' => true
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Storage access failed',
                'details' => config('app.debug') ? $e->getMessage() : 'Storage error'
            ];
        }
    }

    /**
     * Check memory health
     *
     * @return array
     */
    private function checkMemoryHealth(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $memoryUsagePercent = ($memoryUsage / $memoryLimit) * 100;
        
        $status = $memoryUsagePercent > 90 ? 'unhealthy' : 
                 ($memoryUsagePercent > 75 ? 'warning' : 'healthy');
        
        return [
            'status' => $status,
            'usage_bytes' => $memoryUsage,
            'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
            'usage_percent' => round($memoryUsagePercent, 2)
        ];
    }

    /**
     * Get memory limit in bytes
     *
     * @return int
     */
    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit == -1) {
            return PHP_INT_MAX; // No limit
        }
        
        // Convert to bytes
        $lastChar = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $numeric = (int)substr($memoryLimit, 0, -1);
        
        return match ($lastChar) {
            'g' => $numeric * 1024 * 1024 * 1024,
            'm' => $numeric * 1024 * 1024,
            'k' => $numeric * 1024,
            default => (int)$memoryLimit,
        };
    }
}