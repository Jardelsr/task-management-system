<?php

namespace App\Services;

use App\Models\TaskLog;
use App\Services\LogServiceInterface;
use App\Repositories\LogRepositoryInterface;
use App\Exceptions\LoggingException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Enhanced LogService with fallback logging capabilities
 */
class EnhancedLogService implements LogServiceInterface
{
    protected LogRepositoryInterface $logRepository;

    public function __construct(LogRepositoryInterface $logRepository)
    {
        $this->logRepository = $logRepository;
    }

    /**
     * Create a new log entry with fallback to MySQL
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
    ): TaskLog {
        try {
            // Try primary MongoDB logging
            return $this->createMongoDBLog($taskId, $action, $data, $userId, $description);
        } catch (\Exception $e) {
            // Log the MongoDB failure
            Log::warning('MongoDB logging failed, using fallback', [
                'task_id' => $taskId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            // Fallback to MySQL logging
            return $this->createMySQLFallbackLog($taskId, $action, $data, $userId, $description);
        }
    }

    /**
     * Primary MongoDB logging method
     */
    private function createMongoDBLog(
        int $taskId,
        string $action,
        array $data,
        ?int $userId,
        ?string $description
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

        return $this->logRepository->create($logData);
    }

    /**
     * Fallback MySQL logging method
     */
    private function createMySQLFallbackLog(
        int $taskId,
        string $action,
        array $data,
        ?int $userId,
        ?string $description
    ): TaskLog {
        try {
            // Sanitize inputs to prevent SQL injection
            $sqlProtectionService = app(\App\Services\SqlInjectionProtectionService::class);
            
            $sanitizedData = [
                'task_id' => $taskId,
                'action' => $sqlProtectionService->sanitizeInput($action, 'mysql_fallback.action'),
                'user_id' => $userId,
                'data' => json_encode($sqlProtectionService->sanitizeInput($data, 'mysql_fallback.data')),
                'description' => $sqlProtectionService->sanitizeInput($description, 'mysql_fallback.description'),
                'ip_address' => $sqlProtectionService->sanitizeInput(request()->ip(), 'mysql_fallback.ip'),
                'user_agent' => $sqlProtectionService->sanitizeInput(request()->userAgent(), 'mysql_fallback.user_agent'),
                'request_id' => $sqlProtectionService->sanitizeInput(request()->header('X-Request-ID') ?? uniqid(), 'mysql_fallback.request_id'),
                'method' => $sqlProtectionService->sanitizeInput(request()->method(), 'mysql_fallback.method'),
                'url' => $sqlProtectionService->sanitizeInput(request()->url(), 'mysql_fallback.url'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
            
            // Insert into a MySQL fallback table
            $logId = DB::connection('mysql')->table('task_logs_fallback')->insertGetId($sanitizedData);

            // Create a TaskLog object to maintain interface compatibility
            $taskLog = new TaskLog();
            $taskLog->_id = (string)$logId;
            $taskLog->task_id = $taskId;
            $taskLog->action = $action;
            $taskLog->user_id = $userId;
            $taskLog->data = $data;
            $taskLog->description = $description;
            $taskLog->created_at = Carbon::now();

            return $taskLog;

        } catch (\Exception $e) {
            // Last resort: file logging
            Log::error('All logging methods failed', [
                'task_id' => $taskId,
                'action' => $action,
                'mysql_error' => $e->getMessage(),
                'data' => $data
            ]);

            // Return a minimal TaskLog object
            $taskLog = new TaskLog();
            $taskLog->_id = uniqid();
            $taskLog->task_id = $taskId;
            $taskLog->action = $action;
            $taskLog->created_at = Carbon::now();

            return $taskLog;
        }
    }

    /**
     * Create a task activity log with standardized format
     */
    public function createTaskActivityLog(
        int $taskId,
        string $action,
        array $oldData = [],
        array $newData = [],
        ?int $userId = null
    ): TaskLog {
        $logData = [
            'old_data' => $oldData,
            'new_data' => $newData,
            'metadata' => [
                'timestamp' => Carbon::now()->toISOString(),
                'user_id' => $userId,
                'request_id' => request()->header('X-Request-ID') ?? uniqid(),
            ]
        ];

        $description = $this->generateActivityDescription($action, $oldData, $newData);

        return $this->createLog($taskId, $action, $logData, $userId, $description);
    }

    /**
     * Generate a human-readable description for activity logs
     */
    private function generateActivityDescription(string $action, array $oldData, array $newData): string
    {
        switch ($action) {
            case TaskLog::ACTION_CREATED:
                return 'Task was created with initial data';
            case TaskLog::ACTION_UPDATED:
                $changes = array_keys(array_diff_assoc($newData, $oldData));
                return 'Task was updated. Changed fields: ' . implode(', ', $changes);
            case TaskLog::ACTION_DELETED:
                return 'Task was soft deleted and moved to trash';
            case TaskLog::ACTION_RESTORED:
                return 'Task was restored from trash';
            case TaskLog::ACTION_FORCE_DELETED:
                return 'Task was permanently deleted';
            default:
                return "Task action: {$action}";
        }
    }

    /**
     * Get task logs (implementation would need to handle both MongoDB and MySQL fallback)
     */
    public function getTaskLogs(int $taskId, int $limit = 50, int $page = 1): Collection
    {
        try {
            return $this->logRepository->findByTask($taskId, $limit);
        } catch (\Exception $e) {
            // Fallback to MySQL query with SQL injection protection
            $sqlProtectionService = app(\App\Services\SqlInjectionProtectionService::class);
            
            // Sanitize inputs
            $sanitizedTaskId = $sqlProtectionService->sanitizeInput($taskId, 'mysql_query.task_id');
            $sanitizedLimit = $sqlProtectionService->sanitizeLimit($limit, 1000);
            $sanitizedOffset = $sqlProtectionService->sanitizeOffset(($page - 1) * $sanitizedLimit);

            $logs = DB::connection('mysql')
                ->table('task_logs_fallback')
                ->where('task_id', '=', $sanitizedTaskId)
                ->orderBy('created_at', 'desc')
                ->limit($sanitizedLimit)
                ->offset($sanitizedOffset)
                ->get();

            return $logs->map(function ($log) {
                $taskLog = new TaskLog();
                $taskLog->_id = (string)$log->id;
                $taskLog->task_id = $log->task_id;
                $taskLog->action = $log->action;
                $taskLog->user_id = $log->user_id;
                $taskLog->data = json_decode($log->data, true);
                $taskLog->description = $log->description;
                $taskLog->created_at = Carbon::parse($log->created_at);
                return $taskLog;
            });
        }
    }

    // Implement other interface methods with similar fallback logic...
    public function getRecentLogs(int $limit = 100): Collection
    {
        try {
            return $this->logRepository->findRecent($limit);
        } catch (\Exception $e) {
            return collect([]); // Return empty collection on failure
        }
    }

    public function getLogStatistics(): array
    {
        try {
            return $this->logRepository->getStatistics();
        } catch (\Exception $e) {
            return ['total' => 0, 'actions' => []];
        }
    }
}