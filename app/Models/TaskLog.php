<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Carbon\Carbon;

class TaskLog extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * The table associated with the model (MongoDB collection).
     *
     * @var string
     */
    protected $table = 'task_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_id',          // ID da tarefa relacionada
        'action',           // Ação realizada (created, updated, deleted)
        'old_data',         // Dados anteriores (para update e delete)
        'new_data',         // Novos dados (para create e update)
        'user_id',          // ID do usuário que executou a ação
        'user_name',        // Nome do usuário para referência
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'task_id' => 'integer',
        'user_id' => 'integer',
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Log action constants - Essential operations only
     */
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';
    const ACTION_RESTORED = 'restored';
    const ACTION_FORCE_DELETED = 'force_deleted';

    /**
     * Get available log actions
     *
     * @return array
     */
    public static function getAvailableActions(): array
    {
        return [
            self::ACTION_CREATED,
            self::ACTION_UPDATED,
            self::ACTION_DELETED,
            self::ACTION_RESTORED,
            self::ACTION_FORCE_DELETED,
        ];
    }

    /**
     * Check if the log action is valid
     *
     * @param string $action
     * @return bool
     */
    public static function isValidAction(string $action): bool
    {
        return in_array($action, self::getAvailableActions());
    }

    /**
     * Create a log entry for task creation
     *
     * @param int $taskId
     * @param array $taskData
     * @param array $userInfo
     * @return static
     */
    public static function logCreated(int $taskId, array $taskData, array $userInfo = []): self
    {
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_CREATED,
            'old_data' => [],
            'new_data' => $taskData,
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userInfo['user_name'] ?? 'System',
        ]);
    }

    /**
     * Create a log entry for task update
     *
     * @param int $taskId
     * @param array $oldData
     * @param array $newData
     * @param array $userInfo
     * @return static
     */
    public static function logUpdated(int $taskId, array $oldData, array $newData, array $userInfo = []): self
    {
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_UPDATED,
            'old_data' => $oldData,
            'new_data' => $newData,
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userInfo['user_name'] ?? 'System',
        ]);
    }

    /**
     * Create a log entry for task deletion
     *
     * @param int $taskId
     * @param array $taskData
     * @param array $userInfo
     * @return static
     */
    public static function logDeleted(int $taskId, array $taskData, array $userInfo = []): self
    {
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_DELETED,
            'old_data' => $taskData,
            'new_data' => [],
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userInfo['user_name'] ?? 'System',
        ]);
    }

    /**
     * Log task restore operation
     *
     * @param int $taskId
     * @param array $taskData
     * @param array $userInfo
     * @return self
     */
    public static function logRestored(int $taskId, array $taskData, array $userInfo = []): self
    {
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_RESTORED,
            'old_data' => [],
            'new_data' => $taskData,
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userInfo['user_name'] ?? 'System',
        ]);
    }

    /**
     * Log task force delete operation (permanent deletion)
     *
     * @param int $taskId
     * @param array $taskData
     * @param array $userInfo
     * @return self
     */
    public static function logForceDeleted(int $taskId, array $taskData, array $userInfo = []): self
    {
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_FORCE_DELETED,
            'old_data' => $taskData,
            'new_data' => [],
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userInfo['user_name'] ?? 'System',
        ]);
    }

    /**
     * Get logs for a specific task
     *
     * @param int $taskId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTaskLogs(int $taskId, int $limit = 50)
    {
        return self::where('task_id', $taskId)
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Get recent logs across all tasks
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getRecentLogs(int $limit = 100)
    {
        return self::orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Get logs by action type
     *
     * @param string $action
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getLogsByAction(string $action, int $limit = 100)
    {
        return self::where('action', $action)
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Get logs within date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getLogsByDateRange(Carbon $startDate, Carbon $endDate, int $limit = 1000)
    {
        return self::whereBetween('created_at', [$startDate, $endDate])
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Get formatted log message
     *
     * @return string
     */
    public function getFormattedMessageAttribute(): string
    {
        $userName = $this->user_name ?? 'System';
        
        switch ($this->action) {
            case self::ACTION_CREATED:
                return "{$userName} created task #{$this->task_id}";
                
            case self::ACTION_UPDATED:
                return "{$userName} updated task #{$this->task_id}";
                
            case self::ACTION_DELETED:
                return "{$userName} deleted task #{$this->task_id}";
                
            default:
                return "{$userName} performed {$this->action} on task #{$this->task_id}";
        }
    }

    /**
     * Scope to filter by task ID
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param int $taskId
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeForTask($query, int $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Scope to filter by action
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $action
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}