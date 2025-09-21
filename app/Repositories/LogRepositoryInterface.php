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
     * Find a log by ID
     *
     * @param string $id
     * @return TaskLog|null
     */
    public function findById(string $id): ?TaskLog;

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
     * Find logs with filters and pagination
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return Collection<int, TaskLog>
     */
    public function findWithFilters(array $filters, int $limit = 100, int $offset = 0): Collection;

    /**
     * Count logs with filters
     *
     * @param array $filters
     * @return int
     */
    public function countWithFilters(array $filters): int;

    /**
     * Count all logs or logs with specific action
     *
     * @param string|null $action
     * @return int
     */
    public function countAll(?string $action = null): int;

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

    /**
     * Get total count of logs with filters
     *
     * @param array $filters
     * @return int
     */
    public function getTotalCount(array $filters = []): int;

    /**
     * Get comprehensive log statistics
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    public function getStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array;

    /**
     * Clean up old logs based on retention policy
     *
     * @param int $retentionDays
     * @return int Number of deleted logs
     */
    public function cleanupOldLogs(int $retentionDays = 90): int;

    /**
     * Export logs to array format
     *
     * @param array $filters
     * @return array
     */
    public function exportLogs(array $filters = []): array;

    /**
     * Find logs by task ID and multiple actions
     *
     * @param int $taskId
     * @param array $actions
     * @param int $limit
     * @return Collection
     */
    public function findByTaskAndActions(int $taskId, array $actions, int $limit = 50): Collection;

    /**
     * Find recent logs by multiple actions
     *
     * @param array $actions
     * @param int $limit
     * @return Collection
     */
    public function findRecentByActions(array $actions, int $limit = 100): Collection;

    /**
     * Find logs by actions between dates
     *
     * @param array $actions
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection
     */
    public function findByActionsBetweenDates(array $actions, Carbon $startDate, Carbon $endDate): Collection;
}