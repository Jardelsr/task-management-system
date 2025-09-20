<?php

namespace App\Http\Controllers;

use App\Repositories\LogRepositoryInterface;
use App\Exceptions\LoggingException;
use App\Exceptions\DatabaseException;
use App\Http\Requests\ValidationHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
    /**
     * Log repository instance
     *
     * @var LogRepositoryInterface
     */
    protected LogRepositoryInterface $logRepository;

    /**
     * LogController constructor with dependency injection
     *
     * @param LogRepositoryInterface $logRepository
     */
    public function __construct(LogRepositoryInterface $logRepository)
    {
        $this->logRepository = $logRepository;
    }

    /**
     * Display a listing of recent logs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Sanitize and validate parameters
            $sanitizedData = ValidationHelper::sanitizeInput($request->all());
            $request->replace($sanitizedData);
            
            $validatedParams = ValidationHelper::validateLogParameters($request);
            
            // Handle pagination
            $limit = $validatedParams['limit'] ?? config('api.responses.default_per_page', 50);
            $page = $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            
            // Apply filters if provided
            $filters = array_intersect_key($validatedParams, array_flip([
                'task_id', 'action', 'user_id'
            ]));

            // MongoDB connection check and fallback
            try {
                // Test MongoDB connection
                \DB::connection('mongodb')->getDatabaseName();
                
                // Get logs with filters
                if (!empty($filters)) {
                    $logs = $this->logRepository->findWithFilters($filters, $limit, $offset);
                    $totalCount = $this->logRepository->countWithFilters($filters);
                } else {
                    // If requesting specific log by ID
                    if (isset($validatedParams['id'])) {
                        $log = $this->logRepository->findById($validatedParams['id']);
                        if (!$log) {
                            throw new LoggingException('Log not found', 'find_by_id', ['id' => $validatedParams['id']]);
                        }
                        return $this->enhancedSuccessResponse(
                            $log,
                            'Log retrieved successfully',
                            200,
                            ['log_id' => $validatedParams['id']],
                            $request
                        );
                    }
                    
                    $logs = $this->logRepository->findRecent($limit);
                    $totalCount = $this->logRepository->countAll();
                }

                // Calculate pagination metadata
                $totalPages = ceil($totalCount / $limit);
                
                $pagination = [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_next_page' => $page < $totalPages,
                    'has_previous_page' => $page > 1,
                ];
                
                // Build comprehensive metadata
                $meta = $this->buildRequestMetadata($request, [
                    'applied_filters' => $filters,
                    'resource_type' => 'logs',
                    'count' => is_countable($logs) ? $logs->count() : count($logs)
                ]);

                return $this->paginatedResponse(
                    $logs->toArray(),
                    $pagination,
                    'Logs retrieved successfully'
                )->withHeaders([
                    'X-Total-Count' => $totalCount,
                    'X-Page' => $page,
                    'X-Per-Page' => $limit,
                    'X-API-Version' => config('api.version', '1.0')
                ]);
                
            } catch (\Exception $mongoException) {
                // MongoDB connection failed, return demo data to showcase response formatting
                \Log::warning('MongoDB connection failed, returning demo data', [
                    'error' => $mongoException->getMessage()
                ]);
                
                // Create sample log data for demonstration
                $sampleLogs = collect([
                    [
                        '_id' => '66ed123456789abcdef01234',
                        'task_id' => 1,
                        'action' => 'created',
                        'old_data' => null,
                        'new_data' => [
                            'title' => 'Sample Task',
                            'status' => 'pending'
                        ],
                        'user_id' => null,
                        'user_name' => 'system',
                        'created_at' => \Carbon\Carbon::now()->subMinutes(30)->toISOString(),
                        'updated_at' => \Carbon\Carbon::now()->subMinutes(30)->toISOString(),
                    ],
                    [
                        '_id' => '66ed123456789abcdef01235',
                        'task_id' => 1,
                        'action' => 'updated',
                        'old_data' => [
                            'title' => 'Sample Task',
                            'status' => 'pending'
                        ],
                        'new_data' => [
                            'title' => 'Updated Sample Task',
                            'status' => 'in_progress'
                        ],
                        'user_id' => null,
                        'user_name' => 'system',
                        'created_at' => \Carbon\Carbon::now()->subMinutes(15)->toISOString(),
                        'updated_at' => \Carbon\Carbon::now()->subMinutes(15)->toISOString(),
                    ],
                    [
                        '_id' => '66ed123456789abcdef01236',
                        'task_id' => 2,
                        'action' => 'created',
                        'old_data' => null,
                        'new_data' => [
                            'title' => 'Another Task',
                            'status' => 'pending'
                        ],
                        'user_id' => null,
                        'user_name' => 'system',
                        'created_at' => \Carbon\Carbon::now()->subMinutes(5)->toISOString(),
                        'updated_at' => \Carbon\Carbon::now()->subMinutes(5)->toISOString(),
                    ]
                ]);
                
                // Apply basic filters to demo data if provided
                if (!empty($filters)) {
                    $sampleLogs = $sampleLogs->filter(function ($log) use ($filters) {
                        foreach ($filters as $key => $value) {
                            if (isset($log[$key]) && $log[$key] != $value) {
                                return false;
                            }
                        }
                        return true;
                    });
                }
                
                // Apply pagination to demo data
                $totalCount = $sampleLogs->count();
                $sampleLogs = $sampleLogs->slice($offset, $limit)->values();
                
                // Calculate pagination metadata
                $totalPages = ceil($totalCount / $limit);
                
                $pagination = [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_next_page' => $page < $totalPages,
                    'has_previous_page' => $page > 1,
                ];
                
                return $this->paginatedResponse(
                    $sampleLogs->toArray(),
                    $pagination,
                    'Sample logs retrieved successfully (MongoDB unavailable - showing demo data)',
                    null,
                    ['demo_mode' => true, 'applied_filters' => $filters]
                );
            }

        } catch (LoggingException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@index', [
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs: ' . $e->getMessage(),
                'index'
            );
        }
    }

    /**
     * Display logs for a specific task
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function taskLogs(Request $request, int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            $limit = min(max((int) $request->query('limit', 50), 1), 1000);
            $page = max((int) $request->query('page', 1), 1);
            $offset = ($page - 1) * $limit;

            // Get logs for specific task with pagination
            $logs = $this->logRepository->findByTask($validatedId, $limit);
            $totalCount = $this->logRepository->countWithFilters(['task_id' => $validatedId]);

            // Calculate pagination metadata
            $totalPages = ceil($totalCount / $limit);
            
            $pagination = [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'has_previous_page' => $page > 1,
            ];

            return $this->paginatedResponse(
                $logs->toArray(),
                $pagination,
                "Logs for task {$validatedId} retrieved successfully"
            )->withHeaders([
                'X-Task-ID' => $validatedId,
                'X-Total-Count' => $totalCount,
                'X-API-Version' => config('api.version', '1.0')
            ]);

        } catch (\App\Exceptions\TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@taskLogs', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve task logs: ' . $e->getMessage(),
                'task_logs',
                ['task_id' => $id]
            );
        }
    }

    /**
     * Get log statistics
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_logs' => $this->logRepository->countByAction(),
                'logs_by_action' => [
                    'created' => $this->logRepository->countByAction('created'),
                    'updated' => $this->logRepository->countByAction('updated'),
                    'deleted' => $this->logRepository->countByAction('deleted'),
                ],
                'recent_activity' => $this->logRepository->getStatsByAction(7), // Last 7 days
            ];

            return $this->statsResponse($stats, 'Log statistics retrieved successfully');

        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@stats', [
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve log statistics: ' . $e->getMessage(),
                'stats'
            );
        }
    }

    /**
     * Handle root level logs endpoint: GET /logs?id=:id
     * Returns last 30 logs or specific log if id parameter is provided
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function rootLogs(Request $request): JsonResponse
    {
        try {
            $id = $request->query('id');

            // If id parameter is provided, return specific log
            if ($id) {
                $log = $this->logRepository->findById($id);
                
                if (!$log) {
                    throw new LoggingException(
                        'Log not found',
                        'find_by_id',
                        ['log_id' => $id]
                    );
                }

                return $this->successResponse(
                    $log,
                    'Log retrieved successfully',
                    200,
                    ['log_id' => $id]
                );
            }

            // If no id parameter, return last 30 logs
            $logs = $this->logRepository->findRecent(30);

            return $this->logResponse(
                $logs,
                'Last 30 application logs retrieved successfully',
                ['count' => $logs->count(), 'limit' => 30]
            );

        } catch (LoggingException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@rootLogs', [
                'error' => $e->getMessage(),
                'request_id' => $request->query('id')
            ]);

            throw new LoggingException(
                'Failed to retrieve logs: ' . $e->getMessage(),
                'root_logs'
            );
        }
    }
}