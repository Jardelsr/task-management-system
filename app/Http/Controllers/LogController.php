<?php

namespace App\Http\Controllers;

use App\Services\LogServiceInterface;
use App\Exceptions\LoggingException;
use App\Exceptions\DatabaseException;
use App\Exceptions\TaskValidationException;
use App\Http\Requests\ValidationHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class LogController extends Controller
{
    /**
     * Log service instance
     *
     * @var LogServiceInterface
     */
    protected LogServiceInterface $logService;

    /**
     * LogController constructor with dependency injection
     *
     * @param LogServiceInterface $logService
     */
    public function __construct(LogServiceInterface $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Display a listing of logs with comprehensive filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // If requesting specific log by ID via query parameter
            if ($request->has('id')) {
                $id = trim($request->get('id'));
                
                if (empty($id)) {
                    throw new LoggingException(
                        'Log ID parameter cannot be empty',
                        'validation',
                        ['provided_id' => $request->get('id')]
                    );
                }

                $result = $this->logService->findFormattedLogById($id, [
                    'include_metadata' => true,
                    'include_technical' => $request->query('include_technical', false)
                ]);
                
                if (!$result) {
                    throw new LoggingException(
                        'Log not found', 
                        'find_by_id', 
                        ['id' => $id]
                    );
                }
                
                return $this->successResponse(
                    $result['log'],
                    'Log retrieved successfully',
                    200,
                    $result['meta']
                )->withHeaders([
                    'X-Log-ID' => $id,
                    'X-API-Version' => config('api.version', '1.0'),
                    'X-Retrieved-At' => \Carbon\Carbon::now()->toISOString()
                ]);
            }

            // Sanitize and validate request parameters with graceful fallbacks
            $sanitizedData = ValidationHelper::sanitizeInput($request->all());
            
            // Handle limit parameter gracefully
            if (isset($sanitizedData['limit'])) {
                $limit = $sanitizedData['limit'];
                if (!is_numeric($limit) || $limit < 1) {
                    $sanitizedData['limit'] = 50; // Default fallback
                } else {
                    $sanitizedData['limit'] = min(max((int) $limit, 1), 1000); // Enforce bounds
                }
            }
            
            // Handle page parameter gracefully
            if (isset($sanitizedData['page'])) {
                $page = $sanitizedData['page'];
                if (!is_numeric($page) || $page < 1) {
                    $sanitizedData['page'] = 1; // Default fallback
                } else {
                    $sanitizedData['page'] = max((int) $page, 1);
                }
            }
            
            $request->replace($sanitizedData);

            // Use selective validation only for critical parameters
            $criticalValidationRules = [
                'start_date' => ['date', 'date_format:Y-m-d H:i:s'],
                'end_date' => ['date', 'date_format:Y-m-d H:i:s', 'after:start_date'],
                'sort_by' => ['string', Rule::in(['created_at', 'action', 'task_id', 'user_id'])],
                'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            ];

            $validator = app('validator')->make(
                $request->only(array_keys($criticalValidationRules)),
                $criticalValidationRules,
                \App\Http\Requests\LogValidationRequest::getFilterValidationMessages()
            );

            if ($validator->fails()) {
                throw new TaskValidationException(
                    $validator->errors()->toArray(),
                    null,
                    'Log filtering parameters validation failed'
                );
            }

            $validatedParams = array_merge($sanitizedData, $validator->validated());
            
            // Response formatting options
            $responseOptions = [
                'include_metadata' => $request->query('include_metadata', true),
                'include_technical' => $request->query('include_technical', false),
                'date_format' => $request->query('date_format', 'iso8601'),
                'include_changes' => $request->query('include_changes', true)
            ];
            
            // Get logs with enhanced formatting
            $result = $this->logService->getFormattedLogsWithAdvancedFilters($validatedParams, $request, $responseOptions);
            
            // Build enhanced response using the new formatted data
            $response = $this->enhancedPaginatedResponse(
                $result['logs'],
                $result['pagination'],
                $result['statistics'],
                $result['applied_filters'],
                count($result['logs']) === 0 ? 'No logs found matching the specified criteria' : 'Logs retrieved successfully'
            );

            // Add comprehensive headers
            $headers = [
                'X-Total-Count' => $result['pagination']['total'],
                'X-Page' => $result['pagination']['current_page'],
                'X-Per-Page' => $result['pagination']['per_page'],
                'X-Total-Pages' => $result['pagination']['last_page'],
                'X-Applied-Filters' => json_encode(array_keys($result['applied_filters'])),
                'X-API-Version' => config('api.version', '1.0'),
                'X-Query-Execution-Time' => round($result['query_metadata']['execution_time'] ?? 0, 4) . 'ms'
            ];

            // Add sorting information to headers
            if (isset($result['query_metadata']['sort_by'])) {
                $headers['X-Sort-By'] = $result['query_metadata']['sort_by'];
                $headers['X-Sort-Order'] = $result['query_metadata']['sort_order'];
            }

            // Add date range to headers if applicable
            if (!empty($result['query_metadata']['date_range']['start']) && !empty($result['query_metadata']['date_range']['end'])) {
                $headers['X-Date-Range'] = $result['query_metadata']['date_range']['start'] . ' to ' . $result['query_metadata']['date_range']['end'];
            }

            return $response->withHeaders($headers);
            
        } catch (TaskValidationException $e) {
            return $this->validationErrorResponse(
                $e->getErrors(),
                'Log filtering validation failed',
                ['applied_filters' => $request->all()]
            );
        } catch (LoggingException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs: ' . $e->getMessage(),
                'index',
                ['request_params' => $request->all()]
            );
        }
    }

    /**
     * Display logs for a specific task
     *
     * @param Request $request
     * @param int $taskId
     * @return JsonResponse
     */
    public function taskLogs(Request $request, int $taskId): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($taskId);
            
            $limit = min(max((int) $request->query('limit', 50), 1), 1000);

            // Response formatting options
            $responseOptions = [
                'include_metadata' => $request->query('include_metadata', true),
                'include_technical' => $request->query('include_technical', false),
                'date_format' => $request->query('date_format', 'iso8601'),
                'include_changes' => $request->query('include_changes', true)
            ];

            // Get formatted logs for specific task
            $result = $this->logService->getFormattedTaskLogs($validatedId, $limit, $responseOptions);
            
            return $this->enhancedSuccessResponse(
                $result['logs'],
                "Logs for task {$validatedId} retrieved successfully",
                200,
                array_merge($result['meta'], $result['task_metadata'] ?? [])
            )->withHeaders([
                'X-Task-ID' => $validatedId,
                'X-Total-Count' => $result['task_metadata']['total_logs_for_task'] ?? 0,
                'X-Returned-Count' => $result['task_metadata']['returned_count'] ?? 0,
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
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $startDate = null;
            $endDate = null;
            
            // Parse date range if provided
            if ($request->has('start_date')) {
                try {
                    $startDate = Carbon::parse($request->get('start_date'));
                } catch (\Exception $e) {
                    throw new TaskValidationException(['start_date' => ['Invalid date format']], 'start_date');
                }
            }
            
            if ($request->has('end_date')) {
                try {
                    $endDate = Carbon::parse($request->get('end_date'));
                } catch (\Exception $e) {
                    throw new TaskValidationException(['end_date' => ['Invalid date format']], 'end_date');
                }
            }

            // Response formatting options
            $responseOptions = [
                'include_performance_metrics' => $request->query('include_performance', false),
                'detailed_breakdown' => $request->query('detailed', true)
            ];
            
            $stats = $this->logService->getFormattedLogStatistics($startDate, $endDate, $responseOptions);

            return $this->statsResponse(
                $stats,
                'Log statistics retrieved successfully'
            )->withHeaders([
                'X-Statistics-Period' => $stats['summary']['period_analyzed']['start'] . ' to ' . $stats['summary']['period_analyzed']['end'],
                'X-Total-Logs' => $stats['summary']['total_logs'],
                'X-API-Version' => config('api.version', '1.0'),
                'X-Generated-At' => $stats['generated_at']
            ]);

        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@stats', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to retrieve log statistics: ' . $e->getMessage(),
                'stats'
            );
        }
    }

    /**
     * Get specific log by ID
     *
     * @param string $id The MongoDB ObjectId of the log
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            // Validate input ID format
            $id = trim($id);
            if (empty($id)) {
                throw new LoggingException(
                    'Log ID is required',
                    'validation',
                    ['provided_id' => $id]
                );
            }

            $responseOptions = [
                'include_metadata' => true,
                'include_technical' => request()->query('include_technical', false),
                'include_changes' => true
            ];

            $result = $this->logService->findFormattedLogById($id, $responseOptions);
            
            if (!$result) {
                throw new LoggingException(
                    'Log not found',
                    'find_by_id',
                    ['log_id' => $id]
                );
            }

            return $this->successResponse(
                $result['log'],
                'Log retrieved successfully',
                200,
                $result['meta']
            )->withHeaders([
                'X-Log-ID' => $id,
                'X-API-Version' => config('api.version', '1.0'),
                'X-Retrieved-At' => $result['meta']['retrieved_at']
            ]);

        } catch (\InvalidArgumentException $e) {
            throw new LoggingException(
                'Invalid log ID format: ' . $e->getMessage(),
                'validation',
                ['log_id' => $id, 'expected_format' => '24-character hexadecimal string']
            );
        } catch (LoggingException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@show', [
                'error' => $e->getMessage(),
                'log_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            throw new LoggingException(
                'Failed to retrieve log: ' . $e->getMessage(),
                'show',
                ['log_id' => $id]
            );
        }
    }

    /**
     * Get logs by action type
     *
     * @param Request $request
     * @param string $action
     * @return JsonResponse
     */
    public function byAction(Request $request, string $action): JsonResponse
    {
        try {
            $limit = min(max((int) $request->query('limit', 50), 1), 1000);
            
            $logs = $this->logService->getLogsByAction($action, $limit);
            
            $pagination = [
                'current_page' => 1,
                'per_page' => $limit,
                'total' => $logs->count(),
                'total_pages' => 1,
                'has_next_page' => false,
                'has_previous_page' => false,
            ];

            return $this->paginatedResponse(
                $logs->toArray(),
                $pagination,
                "Logs for action '{$action}' retrieved successfully"
            )->withHeaders([
                'X-Action-Type' => $action,
                'X-Total-Count' => $logs->count(),
                'X-API-Version' => config('api.version', '1.0')
            ]);

        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@byAction', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs by action: ' . $e->getMessage(),
                'by_action',
                ['action' => $action]
            );
        }
    }

    /**
     * Get logs by user
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function byUser(Request $request, int $userId): JsonResponse
    {
        try {
            $validatedUserId = ValidationHelper::validateTaskId($userId); // Reusing validation logic
            $limit = min(max((int) $request->query('limit', 50), 1), 1000);
            
            $logs = $this->logService->getLogsByUser($validatedUserId, $limit);
            
            $pagination = [
                'current_page' => 1,
                'per_page' => $limit,
                'total' => $logs->count(),
                'total_pages' => 1,
                'has_next_page' => false,
                'has_previous_page' => false,
            ];

            return $this->paginatedResponse(
                $logs->toArray(),
                $pagination,
                "Logs for user {$validatedUserId} retrieved successfully"
            )->withHeaders([
                'X-User-ID' => $validatedUserId,
                'X-Total-Count' => $logs->count(),
                'X-API-Version' => config('api.version', '1.0')
            ]);

        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@byUser', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs by user: ' . $e->getMessage(),
                'by_user',
                ['user_id' => $userId]
            );
        }
    }

    /**
     * Get logs within date range
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dateRange(Request $request): JsonResponse
    {
        try {
            // Validate required parameters
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            
            if (!$startDate || !$endDate) {
                throw new TaskValidationException([
                    'date_range' => ['Both start_date and end_date are required']
                ], 'date_range');
            }

            try {
                $startDate = Carbon::parse($startDate);
                $endDate = Carbon::parse($endDate);
            } catch (\Exception $e) {
                throw new TaskValidationException([
                    'date_format' => ['Invalid date format. Use ISO 8601 format (Y-m-d H:i:s)']
                ], 'date_format');
            }

            if ($startDate->gte($endDate)) {
                throw new TaskValidationException([
                    'date_range' => ['Start date must be before end date']
                ], 'date_range');
            }

            $limit = min(max((int) $request->query('limit', 100), 1), 1000);
            
            $logs = $this->logService->getLogsByDateRange($startDate, $endDate, $limit);
            
            $pagination = [
                'current_page' => 1,
                'per_page' => $limit,
                'total' => $logs->count(),
                'total_pages' => 1,
                'has_next_page' => false,
                'has_previous_page' => false,
            ];

            return $this->paginatedResponse(
                $logs->toArray(),
                $pagination,
                "Logs for date range retrieved successfully"
            )->withHeaders([
                'X-Start-Date' => $startDate->toISOString(),
                'X-End-Date' => $endDate->toISOString(),
                'X-Total-Count' => $logs->count(),
                'X-API-Version' => config('api.version', '1.0')
            ]);

        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@dateRange', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs by date range: ' . $e->getMessage(),
                'date_range'
            );
        }
    }

    /**
     * Get recent logs (last N logs)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request): JsonResponse
    {
        try {
            $limit = min(max((int) $request->query('limit', 100), 1), 1000);
            
            $logs = $this->logService->getRecentLogs($limit);
            
            return $this->successResponse(
                $logs,
                "Last {$limit} logs retrieved successfully",
                200,
                [
                    'count' => $logs->count(),
                    'limit' => $limit,
                    'timestamp' => Carbon::now()->toISOString()
                ]
            );

        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@recent', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to retrieve recent logs: ' . $e->getMessage(),
                'recent'
            );
        }
    }

    /**
     * Export logs based on filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $filters = $request->all();
            
            // Validate and parse date filters if provided
            if (isset($filters['start_date'])) {
                try {
                    $filters['start_date'] = Carbon::parse($filters['start_date']);
                } catch (\Exception $e) {
                    throw new TaskValidationException(['start_date' => ['Invalid date format']], 'start_date');
                }
            }
            
            if (isset($filters['end_date'])) {
                try {
                    $filters['end_date'] = Carbon::parse($filters['end_date']);
                } catch (\Exception $e) {
                    throw new TaskValidationException(['end_date' => ['Invalid date format']], 'end_date');
                }
            }

            $exportData = $this->logService->exportLogs($filters);
            
            return $this->successResponse(
                $exportData,
                'Logs exported successfully',
                200,
                [
                    'total_exported' => count($exportData),
                    'filters_applied' => array_keys($filters),
                    'export_timestamp' => Carbon::now()->toISOString()
                ]
            );

        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@export', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to export logs: ' . $e->getMessage(),
                'export'
            );
        }
    }

    /**
     * Get deletion logs for a specific task
     *
     * @param Request $request
     * @param int $taskId
     * @return JsonResponse
     */
    public function taskDeletionLogs(Request $request, int $taskId): JsonResponse
    {
        try {
            $validatedId = ValidationHelper::validateTaskId($taskId);
            $limit = min(max((int) $request->query('limit', 50), 1), 1000);
            
            $logs = $this->logService->getTaskDeletionLogs($validatedId, $limit);
            
            $pagination = [
                'current_page' => 1,
                'per_page' => $limit,
                'total' => $logs->count(),
                'total_pages' => 1,
                'has_next_page' => false,
                'has_previous_page' => false,
            ];

            return $this->paginatedResponse(
                $logs->toArray(),
                $pagination,
                "Deletion logs for task {$validatedId} retrieved successfully"
            )->withHeaders([
                'X-Task-ID' => $validatedId,
                'X-Log-Type' => 'deletion',
                'X-Total-Count' => $logs->count(),
                'X-API-Version' => config('api.version', '1.0')
            ]);

        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@taskDeletionLogs', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve task deletion logs: ' . $e->getMessage(),
                'task_deletion_logs',
                ['task_id' => $taskId]
            );
        }
    }

    /**
     * Get recent deletion activity across all tasks
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recentDeletions(Request $request): JsonResponse
    {
        try {
            $limit = min(max((int) $request->query('limit', 100), 1), 1000);
            
            $logs = $this->logService->getRecentDeletionActivity($limit);
            
            return $this->successResponse(
                $logs,
                "Recent deletion activity retrieved successfully",
                200,
                [
                    'count' => $logs->count(),
                    'limit' => $limit,
                    'activity_type' => 'deletion',
                    'timestamp' => Carbon::now()->toISOString()
                ]
            );

        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@recentDeletions', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to retrieve recent deletion activity: ' . $e->getMessage(),
                'recent_deletions'
            );
        }
    }

    /**
     * Get deletion statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deletionStats(Request $request): JsonResponse
    {
        try {
            $startDate = null;
            $endDate = null;
            
            if ($request->has('start_date')) {
                try {
                    $startDate = Carbon::parse($request->get('start_date'));
                } catch (\Exception $e) {
                    throw new TaskValidationException(['start_date' => ['Invalid date format']], 'start_date');
                }
            }
            
            if ($request->has('end_date')) {
                try {
                    $endDate = Carbon::parse($request->get('end_date'));
                } catch (\Exception $e) {
                    throw new TaskValidationException(['end_date' => ['Invalid date format']], 'end_date');
                }
            }

            $stats = $this->logService->getDeletionStatistics($startDate, $endDate);

            return $this->successResponse(
                $stats,
                'Deletion statistics retrieved successfully',
                200,
                [
                    'period' => [
                        'start_date' => $startDate?->toISOString(),
                        'end_date' => $endDate?->toISOString()
                    ],
                    'stats_type' => 'deletion'
                ]
            );

        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@deletionStats', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to retrieve deletion statistics: ' . $e->getMessage(),
                'deletion_stats'
            );
        }
    }

    /**
     * Clean up old logs based on retention policy
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $retentionDays = max((int) $request->query('retention_days', 90), 1);
            
            $deletedCount = $this->logService->cleanupOldLogs($retentionDays);
            
            return $this->successResponse(
                [
                    'deleted_logs_count' => $deletedCount,
                    'retention_days' => $retentionDays,
                    'cleanup_date' => Carbon::now()->toISOString()
                ],
                'Log cleanup completed successfully',
                200,
                [
                    'operation' => 'cleanup',
                    'affected_records' => $deletedCount
                ]
            );

        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@cleanup', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to cleanup old logs: ' . $e->getMessage(),
                'cleanup'
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
                $log = $this->logService->findLogById($id);
                
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
            $logs = $this->logService->getRecentLogs(30);

            return $this->successResponse(
                $logs,
                'Last 30 application logs retrieved successfully',
                200,
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