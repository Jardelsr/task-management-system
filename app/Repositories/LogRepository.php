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
        return TaskLog::find($id);
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
        return $this->create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_CREATED,
            'old_data' => null,
            'new_data' => $taskData,
            'user_id' => $userInfo['id'] ?? null,
            'user_name' => $userInfo['name'] ?? null,
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
        return $this->create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => $oldData,
            'new_data' => $newData,
            'user_id' => $userInfo['id'] ?? null,
            'user_name' => $userInfo['name'] ?? null,
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
        return $this->create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_DELETED,
            'old_data' => $taskData,
            'new_data' => null,
            'user_id' => $userInfo['id'] ?? null,
            'user_name' => $userInfo['name'] ?? null,
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
        
        $logsByAction = $query->groupBy('action')
            ->selectRaw('action, count(*) as count')
            ->pluck('count', 'action')
            ->toArray();
            
        $recentActivity = $query->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('action')
            ->selectRaw('action, count(*) as count')
            ->pluck('count', 'action')
            ->toArray();

        return [
            'total_logs' => $totalLogs,
            'logs_by_action' => [
                'created' => $logsByAction['created'] ?? 0,
                'updated' => $logsByAction['updated'] ?? 0,
                'deleted' => $logsByAction['deleted'] ?? 0,
                'restored' => $logsByAction['restored'] ?? 0,
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
}