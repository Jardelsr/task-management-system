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
     */
    public function create(array $data): TaskLog
    {
        return TaskLog::create($data);
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
}