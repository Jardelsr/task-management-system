<?php

namespace App\Services;

use App\Services\LogServiceInterface;
use App\Repositories\LogRepositoryInterface;
use App\Models\TaskLog;
use App\Exceptions\LoggingException;
use App\Exceptions\DatabaseException;
use App\Http\Requests\ValidationHelper;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LogService implements LogServiceInterface
{
    /**
     * Log repository instance
     *
     * @var LogRepositoryInterface
     */
    protected LogRepositoryInterface $logRepository;

    /**
     * LogService constructor with dependency injection
     *
     * @param LogRepositoryInterface $logRepository
     */
    public function __construct(LogRepositoryInterface $logRepository)
    {
        $this->logRepository = $logRepository;
    }

    /**
     * Create a new log entry
     *
     * @param int $taskId
     * @param string $action
     * @param array $data
     * @param int|null $userId
     * @param string|null $description
     * @return TaskLog
     * @throws LoggingException
     */
    public function createLog(
        int $taskId,
        string $action,
        array $data = [],
        ?int $userId = null,
        ?string $description = null
    ): TaskLog {
        $logData = [
            'task_id' => $taskId,
            'action' => $action,
            'user_id' => $userId,
            'data' => $data,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => Carbon::now(),
        ];

        // Add request context if available
        if (request()) {
            $logData['request_id'] = request()->header('X-Request-ID') ?? uniqid();
            $logData['method'] = request()->method();
            $logData['url'] = request()->url();
        }

        return $this->createLogWithRetry($logData, $taskId, $action);
    }

    /**
     * Create a task activity log with standardized format
     *
     * @param int $taskId
     * @param string $action
     * @param array $oldData
     * @param array $newData
     * @param int|null $userId
     * @return TaskLog
     * @throws LoggingException
     */
    public function createTaskActivityLog(
        int $taskId,
        string $action,
        array $oldData = [],
        array $newData = [],
        ?int $userId = null
    ): TaskLog {
        // Calculate changes for audit trail
        $changes = $this->calculateChanges($oldData, $newData);
        
        $description = $this->generateActivityDescription($action, $changes);

        $data = [
            'old_data' => $oldData,
            'new_data' => $newData,
            'changes' => $changes,
            'change_count' => count($changes)
        ];

        return $this->createLog($taskId, $action, $data, $userId, $description);
    }

    /**
     * Get logs with filtering and pagination
     *
     * @param Request $request
     * @return array
     * @throws LoggingException
     */
    public function getLogsWithFilters(Request $request): array
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
            
            // Apply filters based on request parameters
            $logs = $this->applyFiltersAndPagination($validatedParams, $limit, $offset);
            
            // Get total count for pagination
            $total = $this->getTotalLogsCount($validatedParams);
            
            return [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => ceil($total / $limit),
                    'from' => $offset + 1,
                    'to' => min($offset + $limit, $total)
                ],
                'filters' => $validatedParams
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve filtered logs', [
                'filters' => $request->all(),
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs with filters: ' . $e->getMessage(),
                'retrieve',
                ['filters' => $request->all()]
            );
        }
    }

    /**
     * Get logs for a specific task
     *
     * @param int $taskId
     * @param int $limit
     * @return Collection<int, TaskLog>
     * @throws LoggingException
     */
    public function getTaskLogs(int $taskId, int $limit = 50): Collection
    {
        try {
            return $this->logRepository->findByTask($taskId, $limit);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve task logs', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve task logs: ' . $e->getMessage(),
                'retrieve',
                ['task_id' => $taskId]
            );
        }
    }

    /**
     * Get recent system logs
     *
     * @param int $limit
     * @return Collection<int, TaskLog>
     * @throws LoggingException
     */
    public function getRecentLogs(int $limit = 100): Collection
    {
        try {
            return $this->logRepository->findRecent($limit);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve recent logs', [
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve recent logs: ' . $e->getMessage(),
                'retrieve',
                ['limit' => $limit]
            );
        }
    }

    /**
     * Get logs by action type
     *
     * @param string $action
     * @param int $limit
     * @return Collection<int, TaskLog>
     * @throws LoggingException
     */
    public function getLogsByAction(string $action, int $limit = 100): Collection
    {
        try {
            return $this->logRepository->findByAction($action, $limit);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve logs by action', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs by action: ' . $e->getMessage(),
                'retrieve',
                ['action' => $action]
            );
        }
    }

    /**
     * Get logs within a date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $limit
     * @return Collection<int, TaskLog>
     * @throws LoggingException
     */
    public function getLogsByDateRange(Carbon $startDate, Carbon $endDate, int $limit = 100): Collection
    {
        try {
            return $this->logRepository->findByDateRange($startDate, $endDate, $limit);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve logs by date range', [
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString(),
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs by date range: ' . $e->getMessage(),
                'retrieve',
                ['start_date' => $startDate, 'end_date' => $endDate]
            );
        }
    }

    /**
     * Get logs by user ID
     *
     * @param int $userId
     * @param int $limit
     * @return Collection<int, TaskLog>
     * @throws LoggingException
     */
    public function getLogsByUser(int $userId, int $limit = 100): Collection
    {
        try {
            return $this->logRepository->findByUser($userId, $limit);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve logs by user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs by user: ' . $e->getMessage(),
                'retrieve',
                ['user_id' => $userId]
            );
        }
    }

    /**
     * Find a specific log by ID
     *
     * @param string $id
     * @return TaskLog|null
     * @throws LoggingException
     */
    public function findLogById(string $id): ?TaskLog
    {
        try {
            return $this->logRepository->findById($id);
        } catch (\Exception $e) {
            Log::error('Failed to find log by ID', [
                'log_id' => $id,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to find log by ID: ' . $e->getMessage(),
                'retrieve',
                ['log_id' => $id]
            );
        }
    }

    /**
     * Get log statistics
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     * @throws LoggingException
     */
    public function getLogStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        try {
            return $this->logRepository->getStatistics($startDate, $endDate);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve log statistics', [
                'start_date' => $startDate?->toISOString(),
                'end_date' => $endDate?->toISOString(),
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve log statistics: ' . $e->getMessage(),
                'retrieve',
                ['start_date' => $startDate, 'end_date' => $endDate]
            );
        }
    }

    /**
     * Bulk create logs for multiple operations
     *
     * @param array $logsData
     * @return Collection<int, TaskLog>
     * @throws LoggingException
     */
    public function createBulkLogs(array $logsData): Collection
    {
        try {
            $logs = new Collection();
            
            foreach ($logsData as $logData) {
                $log = $this->createLog(
                    $logData['task_id'],
                    $logData['action'],
                    $logData['data'] ?? [],
                    $logData['user_id'] ?? null,
                    $logData['description'] ?? null
                );
                
                $logs->push($log);
            }
            
            return $logs;
        } catch (\Exception $e) {
            Log::error('Failed to create bulk logs', [
                'logs_count' => count($logsData),
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to create bulk logs: ' . $e->getMessage(),
                'bulk_create',
                ['logs_count' => count($logsData)]
            );
        }
    }

    /**
     * Clean up old logs based on retention policy
     *
     * @param int $retentionDays
     * @return int Number of deleted logs
     * @throws LoggingException
     */
    public function cleanupOldLogs(int $retentionDays = 90): int
    {
        try {
            return $this->logRepository->cleanupOldLogs($retentionDays);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old logs', [
                'retention_days' => $retentionDays,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to cleanup old logs: ' . $e->getMessage(),
                'cleanup',
                ['retention_days' => $retentionDays]
            );
        }
    }

    /**
     * Export logs to array format
     *
     * @param array $filters
     * @return array
     * @throws LoggingException
     */
    public function exportLogs(array $filters = []): array
    {
        try {
            return $this->logRepository->exportLogs($filters);
        } catch (\Exception $e) {
            Log::error('Failed to export logs', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to export logs: ' . $e->getMessage(),
                'export',
                ['filters' => $filters]
            );
        }
    }

    /**
     * Calculate changes between old and new data
     *
     * @param array $oldData
     * @param array $newData
     * @return array
     */
    private function calculateChanges(array $oldData, array $newData): array
    {
        $changes = [];
        
        // Find added/changed fields
        foreach ($newData as $key => $newValue) {
            if (!array_key_exists($key, $oldData) || $oldData[$key] !== $newValue) {
                $changes[$key] = [
                    'from' => $oldData[$key] ?? null,
                    'to' => $newValue
                ];
            }
        }
        
        // Find removed fields
        foreach ($oldData as $key => $oldValue) {
            if (!array_key_exists($key, $newData)) {
                $changes[$key] = [
                    'from' => $oldValue,
                    'to' => null
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Generate human-readable activity description
     *
     * @param string $action
     * @param array $changes
     * @return string
     */
    private function generateActivityDescription(string $action, array $changes): string
    {
        $changedFields = array_keys($changes);
        
        if (empty($changedFields)) {
            return ucfirst($action) . ' task with no changes';
        }
        
        $fieldText = count($changedFields) === 1 ? 'field' : 'fields';
        $fieldsString = implode(', ', $changedFields);
        
        return ucfirst($action) . " task - modified $fieldText: $fieldsString";
    }

    /**
     * Apply filters and pagination to log queries
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return Collection
     */
    private function applyFiltersAndPagination(array $filters, int $limit, int $offset): Collection
    {
        // Start with base query
        $query = null;
        
        // Apply specific filters based on parameters
        if (isset($filters['task_id'])) {
            return $this->logRepository->findByTask($filters['task_id'], $limit);
        }
        
        if (isset($filters['action'])) {
            return $this->logRepository->findByAction($filters['action'], $limit);
        }
        
        if (isset($filters['user_id'])) {
            return $this->logRepository->findByUser($filters['user_id'], $limit);
        }
        
        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            return $this->logRepository->findByDateRange(
                Carbon::parse($filters['date_from']),
                Carbon::parse($filters['date_to']),
                $limit
            );
        }
        
        // Default to recent logs
        return $this->logRepository->findRecent($limit);
    }

    /**
     * Get total count of logs for pagination
     *
     * @param array $filters
     * @return int
     */
    private function getTotalLogsCount(array $filters): int
    {
        // This is a simplified implementation
        // In a real scenario, you'd want to add a count method to the repository
        try {
            return $this->logRepository->getTotalCount($filters);
        } catch (\Exception $e) {
            // Fallback to estimated count
            return 1000; // This should be improved based on your actual implementation
        }
    }

    /**
     * Create a comprehensive deletion log entry
     *
     * @param int $taskId Task ID
     * @param string $deletionType Type of deletion: 'soft_delete', 'force_delete', 'restore'
     * @param array $taskData Original task data
     * @param array $context Deletion context and metadata
     * @param int|null $userId User performing the deletion
     * @param string|null $description Custom description
     * @return TaskLog
     * @throws LoggingException
     */
    public function createDeletionLog(
        int $taskId,
        string $deletionType,
        array $taskData,
        array $context = [],
        ?int $userId = null,
        ?string $description = null
    ): TaskLog {
        try {
            // Validate deletion type
            $validTypes = ['soft_delete', 'force_delete', 'restore'];
            if (!in_array($deletionType, $validTypes)) {
                throw new LoggingException(
                    "Invalid deletion type: {$deletionType}",
                    'create_deletion_log',
                    ['task_id' => $taskId, 'deletion_type' => $deletionType]
                );
            }

            // Determine log action based on deletion type
            $action = match ($deletionType) {
                'soft_delete' => TaskLog::ACTION_DELETED,
                'force_delete' => TaskLog::ACTION_FORCE_DELETED,
                'restore' => TaskLog::ACTION_RESTORED,
                default => TaskLog::ACTION_DELETED
            };

            // Generate description if not provided
            if (!$description) {
                $taskTitle = $taskData['title'] ?? "Task #{$taskId}";
                $description = match ($deletionType) {
                    'soft_delete' => "Task '{$taskTitle}' was moved to trash",
                    'force_delete' => "Task '{$taskTitle}' was permanently deleted",
                    'restore' => "Task '{$taskTitle}' was restored from trash",
                    default => "Task '{$taskTitle}' deletion operation: {$deletionType}"
                };
            }

            // Prepare comprehensive log data
            $logData = [
                'deletion_operation' => [
                    'type' => $deletionType,
                    'is_permanent' => $deletionType === 'force_delete',
                    'is_reversible' => in_array($deletionType, ['soft_delete', 'restore']),
                    'performed_at' => Carbon::now()->toISOString(),
                ],
                'task_data' => $taskData,
                'context' => $context,
                'metadata' => [
                    'task_age_days' => isset($taskData['created_at']) 
                        ? Carbon::parse($taskData['created_at'])->diffInDays(Carbon::now())
                        : null,
                    'was_overdue' => isset($taskData['due_date']) && $taskData['due_date'] < Carbon::now(),
                    'priority_level' => $taskData['priority'] ?? 'medium',
                    'completion_status' => $taskData['status'] ?? 'unknown',
                ],
                'security_info' => [
                    'requires_audit' => in_array($deletionType, ['force_delete']) || 
                                       in_array($taskData['priority'] ?? '', ['high', 'urgent']),
                    'retention_policy' => $deletionType === 'soft_delete' ? '30_days' : 'permanent',
                ]
            ];

            return $this->createLog(
                $taskId,
                $action,
                $logData,
                $userId,
                $description
            );

        } catch (LoggingException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create deletion log', [
                'task_id' => $taskId,
                'deletion_type' => $deletionType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new LoggingException(
                'Failed to create deletion log: ' . $e->getMessage(),
                'create_deletion_log',
                ['task_id' => $taskId, 'deletion_type' => $deletionType]
            );
        }
    }

    /**
     * Create a soft delete log entry
     *
     * @param int $taskId Task ID
     * @param array $taskData Task data before deletion
     * @param array $metadata Additional metadata
     * @param int|null $userId User performing the deletion
     * @return TaskLog
     * @throws LoggingException
     */
    public function createSoftDeleteLog(
        int $taskId,
        array $taskData,
        array $metadata = [],
        ?int $userId = null
    ): TaskLog {
        return $this->createDeletionLog(
            $taskId,
            'soft_delete',
            $taskData,
            array_merge($metadata, [
                'recovery_available' => true,
                'retention_days' => 30,
                'restore_endpoint' => "/tasks/{$taskId}/restore",
            ]),
            $userId
        );
    }

    /**
     * Create a force delete (permanent deletion) log entry
     *
     * @param int $taskId Task ID
     * @param array $taskData Task data before permanent deletion
     * @param array $metadata Additional metadata
     * @param int|null $userId User performing the deletion
     * @return TaskLog
     * @throws LoggingException
     */
    public function createForceDeleteLog(
        int $taskId,
        array $taskData,
        array $metadata = [],
        ?int $userId = null
    ): TaskLog {
        return $this->createDeletionLog(
            $taskId,
            'force_delete',
            $taskData,
            array_merge($metadata, [
                'recovery_available' => false,
                'confirmation_required' => true,
                'audit_level' => 'high',
                'data_retention' => 'none',
            ]),
            $userId
        );
    }

    /**
     * Create a restore log entry
     *
     * @param int $taskId Task ID
     * @param array $taskData Restored task data
     * @param array $metadata Additional metadata
     * @param int|null $userId User performing the restoration
     * @return TaskLog
     * @throws LoggingException
     */
    public function createRestoreLog(
        int $taskId,
        array $taskData,
        array $metadata = [],
        ?int $userId = null
    ): TaskLog {
        return $this->createDeletionLog(
            $taskId,
            'restore',
            $taskData,
            array_merge($metadata, [
                'recovered_from' => 'trash',
                'data_integrity' => 'preserved',
                'restored_at' => Carbon::now()->toISOString(),
            ]),
            $userId
        );
    }

    /**
     * Get deletion logs for a specific task
     *
     * @param int $taskId
     * @param int $limit
     * @return Collection
     * @throws LoggingException
     */
    public function getTaskDeletionLogs(int $taskId, int $limit = 50): Collection
    {
        try {
            return $this->logRepository->findByTaskAndActions(
                $taskId,
                [TaskLog::ACTION_DELETED, TaskLog::ACTION_FORCE_DELETED, TaskLog::ACTION_RESTORED],
                $limit
            );
        } catch (\Exception $e) {
            Log::error('Failed to get deletion logs for task', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve deletion logs: ' . $e->getMessage(),
                'get_task_deletion_logs',
                ['task_id' => $taskId]
            );
        }
    }

    /**
     * Get recent deletion activity across all tasks
     *
     * @param int $limit
     * @return Collection
     * @throws LoggingException
     */
    public function getRecentDeletionActivity(int $limit = 100): Collection
    {
        try {
            return $this->logRepository->findRecentByActions(
                [TaskLog::ACTION_DELETED, TaskLog::ACTION_FORCE_DELETED, TaskLog::ACTION_RESTORED],
                $limit
            );
        } catch (\Exception $e) {
            Log::error('Failed to get recent deletion activity', [
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve recent deletion activity: ' . $e->getMessage(),
                'get_recent_deletion_activity',
                []
            );
        }
    }

    /**
     * Get deletion statistics
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     * @throws LoggingException
     */
    public function getDeletionStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        try {
            $startDate = $startDate ?? Carbon::now()->subDays(30);
            $endDate = $endDate ?? Carbon::now();

            $stats = [
                'period' => [
                    'start' => $startDate->toISOString(),
                    'end' => $endDate->toISOString(),
                    'days' => $startDate->diffInDays($endDate),
                ],
                'deletion_counts' => [
                    'soft_deletes' => 0,
                    'force_deletes' => 0,
                    'restores' => 0,
                    'total_deletions' => 0,
                ],
                'daily_breakdown' => [],
                'deletion_reasons' => [],
                'top_deleted_priorities' => [],
            ];

            // Get deletion counts
            $deletionLogs = $this->logRepository->findByActionsBetweenDates(
                [TaskLog::ACTION_DELETED, TaskLog::ACTION_FORCE_DELETED, TaskLog::ACTION_RESTORED],
                $startDate,
                $endDate
            );

            foreach ($deletionLogs as $log) {
                switch ($log->action) {
                    case TaskLog::ACTION_DELETED:
                        $stats['deletion_counts']['soft_deletes']++;
                        break;
                    case TaskLog::ACTION_FORCE_DELETED:
                        $stats['deletion_counts']['force_deletes']++;
                        break;
                    case TaskLog::ACTION_RESTORED:
                        $stats['deletion_counts']['restores']++;
                        break;
                }
                
                $stats['deletion_counts']['total_deletions']++;
                
                // Daily breakdown
                $date = Carbon::parse($log->created_at)->format('Y-m-d');
                if (!isset($stats['daily_breakdown'][$date])) {
                    $stats['daily_breakdown'][$date] = 0;
                }
                $stats['daily_breakdown'][$date]++;
            }

            // Calculate net deletions (deletions minus restores)
            $stats['deletion_counts']['net_deletions'] = 
                $stats['deletion_counts']['soft_deletes'] + 
                $stats['deletion_counts']['force_deletes'] - 
                $stats['deletion_counts']['restores'];

            return $stats;

        } catch (\Exception $e) {
            Log::error('Failed to get deletion statistics', [
                'start_date' => $startDate?->toISOString(),
                'end_date' => $endDate?->toISOString(),
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve deletion statistics: ' . $e->getMessage(),
                'get_deletion_statistics',
                []
            );
        }
    }

    /**
     * Create log entry with retry logic and fallback mechanisms
     *
     * @param array $logData
     * @param int $taskId
     * @param string $action
     * @param int $maxRetries
     * @return TaskLog
     * @throws LoggingException
     */
    private function createLogWithRetry(array $logData, int $taskId, string $action, int $maxRetries = 3): TaskLog
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // First attempt: Use primary MongoDB repository
                return $this->logRepository->create($logData);
                
            } catch (\MongoDB\Driver\Exception\ConnectionException $e) {
                $lastException = $e;
                Log::warning("MongoDB connection failed on attempt {$attempt}/{$maxRetries}", [
                    'task_id' => $taskId,
                    'action' => $action,
                    'error' => $e->getMessage()
                ]);
                
                // Wait before retry (exponential backoff)
                if ($attempt < $maxRetries) {
                    usleep(100000 * pow(2, $attempt - 1)); // 0.1s, 0.2s, 0.4s
                }
                
            } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
                $lastException = $e;
                Log::warning("MongoDB runtime error on attempt {$attempt}/{$maxRetries}", [
                    'task_id' => $taskId,
                    'action' => $action,
                    'error' => $e->getMessage()
                ]);
                
                // For runtime errors, try fallback immediately
                break;
                
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error("Unexpected error during logging attempt {$attempt}/{$maxRetries}", [
                    'task_id' => $taskId,
                    'action' => $action,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // For unexpected errors, try fallback immediately
                break;
            }
        }
        
        // All MongoDB attempts failed, try fallback mechanisms
        return $this->createLogWithFallback($logData, $taskId, $action, $lastException);
    }

    /**
     * Create log entry using fallback mechanisms when primary logging fails
     *
     * @param array $logData
     * @param int $taskId
     * @param string $action
     * @param \Exception|null $originalException
     * @return TaskLog
     * @throws LoggingException
     */
    private function createLogWithFallback(array $logData, int $taskId, string $action, ?\Exception $originalException = null): TaskLog
    {
        Log::info('Attempting fallback logging mechanisms', [
            'task_id' => $taskId,
            'action' => $action,
            'original_error' => $originalException ? $originalException->getMessage() : 'Unknown'
        ]);

        // Fallback 1: Try MySQL fallback table
        try {
            $fallbackData = [
                'task_id' => $logData['task_id'],
                'action' => $logData['action'],
                'user_id' => $logData['user_id'],
                'data' => json_encode($logData['data']),
                'description' => $logData['description'],
                'ip_address' => $logData['ip_address'],
                'user_agent' => $logData['user_agent'],
                'request_id' => $logData['request_id'] ?? null,
                'method' => $logData['method'] ?? null,
                'url' => $logData['url'] ?? null,
                'original_error' => $originalException ? $originalException->getMessage() : null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];

            \DB::table('task_logs_fallback')->insert($fallbackData);
            
            Log::info('Successfully logged to MySQL fallback table', [
                'task_id' => $taskId,
                'action' => $action
            ]);

            // Create a minimal TaskLog object for return compatibility
            return $this->createFallbackTaskLogObject($logData);
            
        } catch (\Exception $e) {
            Log::error('MySQL fallback logging failed', [
                'task_id' => $taskId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback 2: File system logging
        try {
            $this->logToFileSystem($logData, $taskId, $action);
            
            Log::info('Successfully logged to file system fallback', [
                'task_id' => $taskId,
                'action' => $action
            ]);

            // Create a minimal TaskLog object for return compatibility
            return $this->createFallbackTaskLogObject($logData);
            
        } catch (\Exception $e) {
            Log::error('File system fallback logging failed', [
                'task_id' => $taskId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback 3: System error log
        try {
            $errorLogData = [
                'timestamp' => Carbon::now()->toISOString(),
                'task_id' => $taskId,
                'action' => $action,
                'data' => $logData,
                'all_fallbacks_failed' => true
            ];
            
            error_log('TaskLog Fallback: ' . json_encode($errorLogData));
            
            Log::critical('All logging mechanisms failed, logged to system error log', [
                'task_id' => $taskId,
                'action' => $action
            ]);

            // Create a minimal TaskLog object for return compatibility
            return $this->createFallbackTaskLogObject($logData);
            
        } catch (\Exception $e) {
            // This is the last resort - if this fails, we throw the exception
            throw new LoggingException(
                'All logging mechanisms failed including system error log: ' . $e->getMessage(),
                'all_fallbacks_failed',
                [
                    'task_id' => $taskId, 
                    'action' => $action,
                    'original_error' => $originalException ? $originalException->getMessage() : 'Unknown'
                ]
            );
        }
    }

    /**
     * Log to file system as fallback
     *
     * @param array $logData
     * @param int $taskId
     * @param string $action
     * @return void
     * @throws \Exception
     */
    private function logToFileSystem(array $logData, int $taskId, string $action): void
    {
        $logFile = storage_path('logs/task_logs_fallback.log');
        $logEntry = [
            'timestamp' => Carbon::now()->toISOString(),
            'level' => 'INFO',
            'message' => 'Fallback task log entry',
            'context' => [
                'task_id' => $taskId,
                'action' => $action,
                'data' => $logData,
                'source' => 'LogService::logToFileSystem'
            ]
        ];

        $logLine = json_encode($logEntry) . PHP_EOL;
        
        if (file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX) === false) {
            throw new \Exception('Failed to write to fallback log file');
        }
    }

    /**
     * Create a minimal TaskLog object for return compatibility when using fallbacks
     *
     * @param array $logData
     * @return TaskLog
     */
    private function createFallbackTaskLogObject(array $logData): TaskLog
    {
        // Create a minimal TaskLog object that doesn't save to database
        $taskLog = new TaskLog();
        $taskLog->setAttribute('_id', uniqid('fallback_'));
        $taskLog->setAttribute('task_id', $logData['task_id']);
        $taskLog->setAttribute('action', $logData['action']);
        $taskLog->setAttribute('user_id', $logData['user_id']);
        $taskLog->setAttribute('data', $logData['data']);
        $taskLog->setAttribute('description', $logData['description']);
        $taskLog->setAttribute('ip_address', $logData['ip_address']);
        $taskLog->setAttribute('user_agent', $logData['user_agent']);
        $taskLog->setAttribute('created_at', $logData['created_at']);
        $taskLog->setAttribute('fallback_used', true);
        
        return $taskLog;
    }
}