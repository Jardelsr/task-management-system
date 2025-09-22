<?php

namespace App\Repositories;

use App\Models\TaskLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class LogRepository implements LogRepositoryInterface
{
    /**
     * Create a new log entry
     *
     * @param array $data
     * @return TaskLog
     * @throws \Exception
     */
    public function create(array $data): TaskLog
    {
        try {
            return TaskLog::create($data);
        } catch (\MongoDB\Driver\Exception\ConnectionException $e) {
            \Log::error('MongoDB connection failed in LogRepository::create', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e; // Re-throw for LogService to handle with fallback
        } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
            \Log::error('MongoDB runtime error in LogRepository::create', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e; // Re-throw for LogService to handle with fallback
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogRepository::create', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw for LogService to handle with fallback
        }
    }

    /**
     * Find a log by ID
     *
     * @param string $id
     * @return TaskLog|null
     */
    public function findById(string $id): ?TaskLog
    {
        // Validate MongoDB ObjectId format (24 character hex string)
        if (!$this->isValidObjectId($id)) {
            // For better UX, treat invalid ObjectId format as "not found" instead of validation error
            \Log::info('Invalid ObjectId format provided', [
                'provided_id' => $id,
                'expected_format' => '24 character hex string'
            ]);
            return null;
        }

        try {
            return TaskLog::find($id);
        } catch (\Exception $e) {
            // Log the error and return null for invalid IDs
            \Log::warning('Failed to find log by ID', [
                'log_id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validate MongoDB ObjectId format
     *
     * @param string $id
     * @return bool
     */
    private function isValidObjectId(string $id): bool
    {
        return preg_match('/^[a-f\d]{24}$/i', $id) === 1;
    }

    /**
     * Find logs by task ID
     *
     * @param int $taskId
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findByTask(int $taskId, int $limit = 50): Collection
    {
        return TaskLog::forTask($taskId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find recent logs
     *
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findRecent(int $limit = 100): Collection
    {
        return TaskLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find logs by action type
     *
     * @param string $action
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findByAction(string $action, int $limit = 100): Collection
    {
        return TaskLog::byAction($action)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find logs by user ID
     *
     * @param int $userId
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findByUser(int $userId, int $limit = 100): Collection
    {
        return TaskLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find logs within a date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findByDateRange(Carbon $startDate, Carbon $endDate, int $limit = 1000): Collection
    {
        return TaskLog::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find recent logs within specified days
     *
     * @param int $days
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findRecentDays(int $days = 7, int $limit = 100): Collection
    {
        $startDate = Carbon::now()->subDays($days);
        
        return TaskLog::where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Log task creation
     *
     * @param int $taskId
     * @param array $taskData
     * @param array $userInfo
     * @return TaskLog
     */
    public function logCreated(int $taskId, array $taskData, array $userInfo = []): TaskLog
    {
        $userName = $userInfo['user_name'] ?? $userInfo['name'] ?? 'System';
        
        return $this->create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_CREATED,
            'old_data' => [],
            'new_data' => $taskData,
            'user_id' => $userInfo['user_id'] ?? $userInfo['id'] ?? null,
            'user_name' => $userName,
            'description' => "{$userName} created task #{$taskId}",
        ]);
    }

    /**
     * Log task update
     *
     * @param int $taskId
     * @param array $oldData
     * @param array $newData
     * @param array $userInfo
     * @return TaskLog
     */
    public function logUpdated(int $taskId, array $oldData, array $newData, array $userInfo = []): TaskLog
    {
        $userName = $userInfo['user_name'] ?? $userInfo['name'] ?? 'System';
        
        return $this->create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => $oldData,
            'new_data' => $newData,
            'user_id' => $userInfo['user_id'] ?? $userInfo['id'] ?? null,
            'user_name' => $userName,
            'description' => "{$userName} updated task #{$taskId}",
        ]);
    }

    /**
     * Log task deletion
     *
     * @param int $taskId
     * @param array $taskData
     * @param array $userInfo
     * @return TaskLog
     */
    public function logDeleted(int $taskId, array $taskData, array $userInfo = []): TaskLog
    {
        $userName = $userInfo['user_name'] ?? $userInfo['name'] ?? 'System';
        
        return $this->create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_DELETED,
            'old_data' => $taskData,
            'new_data' => [],
            'user_id' => $userInfo['user_id'] ?? $userInfo['id'] ?? null,
            'user_name' => $userName,
            'description' => "{$userName} deleted task #{$taskId}",
        ]);
    }

    /**
     * Count logs by action type
     *
     * @param string|null $action
     * @return int
     */
    public function countByAction(?string $action = null): int
    {
        $query = TaskLog::query();
        
        if ($action !== null) {
            $query->byAction($action);
        }
        
        return $query->count();
    }

    /**
     * Count logs for a specific task
     *
     * @param int $taskId
     * @return int
     */
    public function countByTask(int $taskId): int
    {
        return TaskLog::forTask($taskId)->count();
    }

    /**
     * Get log statistics grouped by action
     *
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getStatsByAction(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        $stats = TaskLog::where('created_at', '>=', $startDate)
            ->get()
            ->groupBy('action')
            ->map(function ($logs) {
                return $logs->count();
            })
            ->toArray();

        // Ensure all actions are represented
        $actions = [
            TaskLog::ACTION_CREATED => 0,
            TaskLog::ACTION_UPDATED => 0,
            TaskLog::ACTION_DELETED => 0,
        ];

        return array_merge($actions, $stats);
    }

    /**
     * Delete old logs beyond retention period
     *
     * @param int $retentionDays
     * @return int Number of deleted logs
     */
    public function deleteOldLogs(int $retentionDays = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        
        $oldLogs = TaskLog::where('created_at', '<', $cutoffDate)->get();
        $count = $oldLogs->count();
        
        TaskLog::where('created_at', '<', $cutoffDate)->delete();
        
        return $count;
    }

    /**
     * Find logs with filters and pagination
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return Collection<int, TaskLog>
     */
    public function findWithFilters(array $filters, int $limit = 100, int $offset = 0): Collection
    {
        $query = TaskLog::query();

        // Apply filters
        if (!empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->orderBy('created_at', 'desc')
                    ->skip($offset)
                    ->take($limit)
                    ->get();
    }

    /**
     * Count logs with filters
     *
     * @param array $filters
     * @return int
     */
    public function countWithFilters(array $filters): int
    {
        $query = TaskLog::query();

        // Apply same filters as findWithFilters
        if (!empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->count();
    }

    /**
     * Count all logs or logs with specific action
     *
     * @param string|null $action
     * @return int
     */
    public function countAll(?string $action = null): int
    {
        $query = TaskLog::query();
        
        if ($action !== null) {
            $query->where('action', $action);
        }
        
        return $query->count();
    }

    /**
     * Get total count of logs with filters
     *
     * @param array $filters
     * @return int
     */
    public function getTotalCount(array $filters = []): int
    {
        return $this->countWithFilters($filters);
    }

    /**
     * Get comprehensive log statistics
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    public function getStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = TaskLog::query();
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        $totalLogs = $query->count();
        
        // Get logs by action using MongoDB aggregation
        $logsByAction = [];
        $actions = ['created', 'updated', 'deleted', 'restored', 'force_deleted', 'update_attempt'];
        
        foreach ($actions as $action) {
            $logsByAction[$action] = TaskLog::where('action', $action)->count();
        }
            
        // Get recent activity (last 7 days) using MongoDB queries
        $recentActivity = [];
        $recentDate = Carbon::now()->subDays(7);
        
        foreach ($actions as $action) {
            $count = TaskLog::where('action', $action)
                ->where('created_at', '>=', $recentDate)
                ->count();
            if ($count > 0) {
                $recentActivity[$action] = $count;
            }
        }

        return [
            'total_logs' => $totalLogs,
            'logs_by_action' => [
                'created' => $logsByAction['created'] ?? 0,
                'updated' => $logsByAction['updated'] ?? 0,
                'deleted' => $logsByAction['deleted'] ?? 0,
                'restored' => $logsByAction['restored'] ?? 0,
                'force_deleted' => $logsByAction['force_deleted'] ?? 0,
                'update_attempt' => $logsByAction['update_attempt'] ?? 0,
            ],
            'recent_activity' => $recentActivity,
            'date_range' => [
                'from' => $startDate?->toISOString(),
                'to' => $endDate?->toISOString()
            ]
        ];
    }

    /**
     * Clean up old logs based on retention policy
     *
     * @param int $retentionDays
     * @return int Number of deleted logs
     */
    public function cleanupOldLogs(int $retentionDays = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        return TaskLog::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Export logs to array format
     *
     * @param array $filters
     * @return array
     */
    public function exportLogs(array $filters = []): array
    {
        $query = TaskLog::query();
        
        // Apply filters
        if (!empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($filters['date_from']),
                Carbon::parse($filters['date_to'])
            ]);
        }
        
        return $query->orderBy('created_at', 'desc')
            ->limit($filters['limit'] ?? 1000)
            ->get()
            ->toArray();
    }

    /**
     * Find logs by task ID and multiple actions
     *
     * @param int $taskId
     * @param array $actions
     * @param int $limit
     * @return Collection
     */
    public function findByTaskAndActions(int $taskId, array $actions, int $limit = 50): Collection
    {
        return TaskLog::where('task_id', $taskId)
            ->whereIn('action', $actions)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find recent logs by multiple actions
     *
     * @param array $actions
     * @param int $limit
     * @return Collection
     */
    public function findRecentByActions(array $actions, int $limit = 100): Collection
    {
        return TaskLog::whereIn('action', $actions)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find logs by actions between dates
     *
     * @param array $actions
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection
     */
    public function findByActionsBetweenDates(array $actions, Carbon $startDate, Carbon $endDate): Collection
    {
        return TaskLog::whereIn('action', $actions)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find logs with advanced filters and sorting
     *
     * @param array $criteria
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @param string $sortBy
     * @param string $sortOrder
     * @param int $limit
     * @param int $offset
     * @return Collection<int, TaskLog>
     */
    public function findWithAdvancedFilters(
        array $criteria,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $limit = 100,
        int $offset = 0
    ): Collection {
        $query = TaskLog::query();

        // Apply criteria filters
        foreach ($criteria as $field => $value) {
            if ($value !== null && $value !== '') {
                if ($field === 'action' && is_array($value)) {
                    $query->whereIn($field, $value);
                } elseif (in_array($field, ['task_id', 'user_id', 'level', 'source', 'action'])) {
                    $query->where($field, $value);
                }
            }
        }

        // Apply date range filter
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        // Apply sorting
        $allowedSortFields = ['created_at', 'action', 'task_id', 'user_id', 'level'];
        if (in_array($sortBy, $allowedSortFields)) {
            $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->offset($offset)->limit($limit)->get();
    }

    /**
     * Get count of logs with specific filters
     *
     * @param array $criteria
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return int
     */
    public function getCountWithFilters(
        array $criteria,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): int {
        $query = TaskLog::query();

        // Apply same criteria filters as in findWithAdvancedFilters
        foreach ($criteria as $field => $value) {
            if ($value !== null && $value !== '') {
                if ($field === 'action' && is_array($value)) {
                    $query->whereIn($field, $value);
                } elseif (in_array($field, ['task_id', 'user_id', 'level', 'source', 'action'])) {
                    $query->where($field, $value);
                }
            }
        }

        // Apply date range filter
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->count();
    }

    /**
     * Get estimated total count of logs
     *
     * @return int
     */
    public function getEstimatedTotalCount(): int
    {
        try {
            return TaskLog::count();
        } catch (\Exception $e) {
            // Fallback to a reasonable estimate
            return 1000;
        }
    }

    /**
     * Find logs with comprehensive response formatting
     *
     * @param array $criteria
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @param string $sortBy
     * @param string $sortOrder
     * @param int $limit
     * @param int $offset
     * @param array $responseOptions
     * @return array
     */
    public function findWithFormattedResponse(
        array $criteria,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $limit = 100,
        int $offset = 0,
        array $responseOptions = []
    ): array {
        // Get the logs collection
        $logs = $this->findWithAdvancedFilters(
            $criteria,
            $startDate,
            $endDate,
            $sortBy,
            $sortOrder,
            $limit,
            $offset
        );

        // Get total count
        $totalCount = $this->getCountWithFilters($criteria, $startDate, $endDate);

        // Calculate pagination
        $currentPage = intval($offset / $limit) + 1;
        $lastPage = $totalCount > 0 ? ceil($totalCount / $limit) : 1;

        $pagination = [
            'current_page' => $currentPage,
            'per_page' => $limit,
            'total' => $totalCount,
            'last_page' => $lastPage,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $totalCount),
            'has_next_page' => $currentPage < $lastPage,
            'has_previous_page' => $currentPage > 1
        ];

        // Format logs using model's response method
        $formattedLogs = $logs->map(function ($log) use ($responseOptions) {
            return $log->toResponseArray($responseOptions);
        });

        // Build query statistics
        $statistics = $this->buildQueryStatistics($logs, $criteria, $totalCount);

        return [
            'logs' => $formattedLogs,
            'pagination' => $pagination,
            'statistics' => $statistics,
            'applied_filters' => array_filter($criteria, function($value) {
                return $value !== null && $value !== '';
            }),
            'query_metadata' => [
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'date_range' => [
                    'start' => $startDate?->toISOString(),
                    'end' => $endDate?->toISOString()
                ],
                'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
            ]
        ];
    }

    /**
     * Build query statistics
     *
     * @param Collection $logs
     * @param array $criteria
     * @param int $totalCount
     * @return array
     */
    protected function buildQueryStatistics(Collection $logs, array $criteria, int $totalCount): array
    {
        $stats = [
            'total_found' => $totalCount,
            'returned_count' => $logs->count(),
            'filtered' => !empty(array_filter($criteria)),
            'has_more' => $logs->count() < $totalCount
        ];

        if ($logs->isNotEmpty()) {
            // Action distribution
            $actionDistribution = $logs->groupBy('action')->map->count();
            $stats['action_distribution'] = $actionDistribution->toArray();

            // User distribution
            $userDistribution = $logs->groupBy('user_id')->map->count();
            $stats['user_distribution'] = [
                'unique_users' => $userDistribution->count(),
                'system_actions' => $logs->whereNull('user_id')->count(),
                'user_actions' => $logs->whereNotNull('user_id')->count()
            ];

            // Date range
            $stats['date_range'] = [
                'oldest' => $logs->min('created_at')?->toISOString(),
                'newest' => $logs->max('created_at')?->toISOString(),
                'span_hours' => $logs->min('created_at')?->diffInHours($logs->max('created_at')) ?? 0
            ];

            // Data analysis
            $stats['data_analysis'] = [
                'logs_with_old_data' => $logs->whereNotNull('old_data')->count(),
                'logs_with_new_data' => $logs->whereNotNull('new_data')->count(),
                'logs_with_changes' => $logs->where(function($log) {
                    return !empty($log->old_data) && !empty($log->new_data);
                })->count()
            ];
        }

        return $stats;
    }

    /**
     * Find single log with formatted response
     *
     * @param string $id
     * @param array $responseOptions
     * @return array|null
     */
    public function findByIdWithFormattedResponse(string $id, array $responseOptions = []): ?array
    {
        $log = $this->findById($id);
        
        if (!$log) {
            return null;
        }

        return [
            'log' => $log->toResponseArray($responseOptions),
            'meta' => [
                'retrieved_at' => Carbon::now()->toISOString(),
                'log_id' => $id,
                'collection' => 'task_logs'
            ]
        ];
    }

    /**
     * Find task logs with formatted response
     *
     * @param int $taskId
     * @param int $limit
     * @param array $responseOptions
     * @return array
     */
    public function findByTaskWithFormattedResponse(int $taskId, int $limit = 50, array $responseOptions = []): array
    {
        $logs = $this->findByTask($taskId, $limit);
        $totalCount = $this->countByTask($taskId);

        $response = TaskLog::toResponseCollection($logs, $responseOptions);
        
        // Add task-specific metadata
        $response['task_metadata'] = [
            'task_id' => $taskId,
            'total_logs_for_task' => $totalCount,
            'returned_count' => $logs->count(),
            'has_more' => $logs->count() < $totalCount,
            'limit_applied' => $limit
        ];

        return $response;
    }

    /**
     * Get statistics with formatted response
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @param array $responseOptions
     * @return array
     */
    public function getStatisticsWithFormattedResponse(
        ?Carbon $startDate = null, 
        ?Carbon $endDate = null,
        array $responseOptions = []
    ): array {
        $stats = $this->getStatistics($startDate, $endDate);
        
        // Enhanced formatting
        $formatted = [
            'summary' => [
                'total_logs' => $stats['total_logs'],
                'period_analyzed' => [
                    'start' => $startDate?->toISOString() ?? 'all_time',
                    'end' => $endDate?->toISOString() ?? 'now',
                    'span' => $this->calculatePeriodSpan($startDate, $endDate)
                ]
            ],
            'actions' => [
                'breakdown' => $this->formatActionBreakdown($stats['logs_by_action']),
                'total_by_type' => $stats['logs_by_action']
            ],
            'activity' => [
                'recent' => $stats['recent_activity'],
                'trend' => $this->calculateActivityTrend($stats['recent_activity'])
            ],
            'generated_at' => Carbon::now()->toISOString(),
            'metadata' => [
                'data_source' => 'mongodb',
                'collection' => 'task_logs',
                'freshness' => 'real_time'
            ]
        ];

        return $formatted;
    }

    /**
     * Format action breakdown for statistics
     *
     * @param array $actionStats
     * @return array
     */
    protected function formatActionBreakdown(array $actionStats): array
    {
        $total = array_sum($actionStats);
        $breakdown = [];
        
        foreach ($actionStats as $action => $count) {
            $breakdown[] = [
                'action' => $action,
                'action_display' => $this->getActionDisplayName($action),
                'count' => (int) $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0
            ];
        }

        // Sort by count descending
        usort($breakdown, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $breakdown;
    }

    /**
     * Get action display name
     *
     * @param string $action
     * @return string
     */
    protected function getActionDisplayName(string $action): string
    {
        $displayNames = [
            TaskLog::ACTION_CREATED => 'Created',
            TaskLog::ACTION_UPDATED => 'Updated',
            TaskLog::ACTION_DELETED => 'Deleted',
            TaskLog::ACTION_RESTORED => 'Restored',
            TaskLog::ACTION_FORCE_DELETED => 'Permanently Deleted'
        ];

        return $displayNames[$action] ?? ucfirst($action);
    }

    /**
     * Calculate period span for statistics
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return string
     */
    protected function calculatePeriodSpan(?Carbon $startDate, ?Carbon $endDate): string
    {
        if (!$startDate && !$endDate) {
            return 'all_time';
        }

        if (!$startDate) {
            return 'up_to_' . $endDate->toDateString();
        }

        if (!$endDate) {
            return 'from_' . $startDate->toDateString();
        }

        return $startDate->diffForHumans($endDate, true);
    }

    /**
     * Calculate activity trend
     *
     * @param array $recentActivity
     * @return string
     */
    protected function calculateActivityTrend(array $recentActivity): string
    {
        $total = array_sum($recentActivity);
        
        if ($total === 0) {
            return 'inactive';
        }

        if ($total < 10) {
            return 'low';
        }

        if ($total < 50) {
            return 'moderate';
        }

        return 'high';
    }
}