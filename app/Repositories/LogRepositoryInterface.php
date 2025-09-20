<?php

namespace App\Repositories;

use App\Models\TaskLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface LogRepositoryInterface
{
    /**
     * Create a new log entry
     *
     * @param array $data
     * @return TaskLog
     */
    public function create(array $data): TaskLog;

    /**
     * Find logs by task ID
     *
     * @param int $taskId
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findByTask(int $taskId, int $limit = 50): Collection;

    /**
     * Find recent logs
     *
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findRecent(int $limit = 100): Collection;

    /**
     * Find logs by action type
     *
     * @param string $action
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findByAction(string $action, int $limit = 100): Collection;

    /**
     * Find logs by user ID
     *
     * @param int $userId
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findByUser(int $userId, int $limit = 100): Collection;

    /**
     * Find logs within a date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findByDateRange(Carbon $startDate, Carbon $endDate, int $limit = 1000): Collection;

    /**
     * Find recent logs within specified days
     *
     * @param int $days
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function findRecentDays(int $days = 7, int $limit = 100): Collection;

    /**
     * Log task creation
     *
     * @param int $taskId
     * @param array $taskData
     * @param array $userInfo
     * @return TaskLog
     */
    public function logCreated(int $taskId, array $taskData, array $userInfo = []): TaskLog;

    /**
     * Log task update
     *
     * @param int $taskId
     * @param array $oldData
     * @param array $newData
     * @param array $userInfo
     * @return TaskLog
     */
    public function logUpdated(int $taskId, array $oldData, array $newData, array $userInfo = []): TaskLog;

    /**
     * Log task deletion
     *
     * @param int $taskId
     * @param array $taskData
     * @param array $userInfo
     * @return TaskLog
     */
    public function logDeleted(int $taskId, array $taskData, array $userInfo = []): TaskLog;

    /**
     * Count logs by action type
     *
     * @param string|null $action
     * @return int
     */
    public function countByAction(?string $action = null): int;

    /**
     * Count logs for a specific task
     *
     * @param int $taskId
     * @return int
     */
    public function countByTask(int $taskId): int;

    /**
     * Get log statistics grouped by action
     *
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getStatsByAction(int $days = 30): array;

    /**
     * Delete old logs beyond retention period
     *
     * @param int $retentionDays
     * @return int Number of deleted logs
     */
    public function deleteOldLogs(int $retentionDays = 90): int;
}