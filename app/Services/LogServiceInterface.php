<?php

namespace App\Services;

use App\Models\TaskLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Http\Request;

interface LogServiceInterface
{
    /**
     * Create a new log entry
     *
     * @param int $taskId
     * @param string $action
     * @param array $data
     * @param int|null $userId
     * @param string|null $description
     * @return TaskLog
     */
    public function createLog(
        int $taskId,
        string $action,
        array $data = [],
        ?int $userId = null,
        ?string $description = null
    ): TaskLog;

    /**
     * Create a task activity log with standardized format
     *
     * @param int $taskId
     * @param string $action
     * @param array $oldData
     * @param array $newData
     * @param int|null $userId
     * @return TaskLog
     */
    public function createTaskActivityLog(
        int $taskId,
        string $action,
        array $oldData = [],
        array $newData = [],
        ?int $userId = null
    ): TaskLog;

    /**
     * Get logs with filtering and pagination
     *
     * @param Request $request
     * @return array
     */
    public function getLogsWithFilters(Request $request): array;

    /**
     * Get logs for a specific task
     *
     * @param int $taskId
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function getTaskLogs(int $taskId, int $limit = 50): Collection;

    /**
     * Get recent system logs
     *
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function getRecentLogs(int $limit = 100): Collection;

    /**
     * Get logs by action type
     *
     * @param string $action
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function getLogsByAction(string $action, int $limit = 100): Collection;

    /**
     * Get logs within a date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function getLogsByDateRange(Carbon $startDate, Carbon $endDate, int $limit = 100): Collection;

    /**
     * Get logs by user ID
     *
     * @param int $userId
     * @param int $limit
     * @return Collection<int, TaskLog>
     */
    public function getLogsByUser(int $userId, int $limit = 100): Collection;

    /**
     * Find a specific log by ID
     *
     * @param string $id
     * @return TaskLog|null
     */
    public function findLogById(string $id): ?TaskLog;

    /**
     * Get log statistics
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    public function getLogStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array;

    /**
     * Bulk create logs for multiple operations
     *
     * @param array $logsData
     * @return Collection<int, TaskLog>
     */
    public function createBulkLogs(array $logsData): Collection;

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
     * Create a comprehensive deletion log entry
     *
     * @param int $taskId Task ID
     * @param string $deletionType Type of deletion: 'soft_delete', 'force_delete', 'restore'
     * @param array $taskData Original task data
     * @param array $context Deletion context and metadata
     * @param int|null $userId User performing the deletion
     * @param string|null $description Custom description
     * @return TaskLog
     */
    public function createDeletionLog(
        int $taskId,
        string $deletionType,
        array $taskData,
        array $context = [],
        ?int $userId = null,
        ?string $description = null
    ): TaskLog;

    /**
     * Create a soft delete log entry
     *
     * @param int $taskId Task ID
     * @param array $taskData Task data before deletion
     * @param array $metadata Additional metadata
     * @param int|null $userId User performing the deletion
     * @return TaskLog
     */
    public function createSoftDeleteLog(
        int $taskId,
        array $taskData,
        array $metadata = [],
        ?int $userId = null
    ): TaskLog;

    /**
     * Create a force delete (permanent deletion) log entry
     *
     * @param int $taskId Task ID
     * @param array $taskData Task data before permanent deletion
     * @param array $metadata Additional metadata
     * @param int|null $userId User performing the deletion
     * @return TaskLog
     */
    public function createForceDeleteLog(
        int $taskId,
        array $taskData,
        array $metadata = [],
        ?int $userId = null
    ): TaskLog;

    /**
     * Create a restore log entry
     *
     * @param int $taskId Task ID
     * @param array $taskData Restored task data
     * @param array $metadata Additional metadata
     * @param int|null $userId User performing the restoration
     * @return TaskLog
     */
    public function createRestoreLog(
        int $taskId,
        array $taskData,
        array $metadata = [],
        ?int $userId = null
    ): TaskLog;

    /**
     * Get deletion logs for a specific task
     *
     * @param int $taskId
     * @param int $limit
     * @return Collection
     */
    public function getTaskDeletionLogs(int $taskId, int $limit = 50): Collection;

    /**
     * Get recent deletion activity across all tasks
     *
     * @param int $limit
     * @return Collection
     */
    public function getRecentDeletionActivity(int $limit = 100): Collection;

    /**
     * Get deletion statistics
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    public function getDeletionStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array;
}