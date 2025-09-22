<?php

namespace App\Models;

// Use MongoDB for logging system
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
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection = 'task_logs';

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
        'description',      // Description for the log entry
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
        $userName = $userInfo['user_name'] ?? 'System';
        
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_CREATED,
            'old_data' => [],
            'new_data' => $taskData,
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userName,
            'description' => "{$userName} created task #{$taskId}",
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
        $userName = $userInfo['user_name'] ?? 'System';
        
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_UPDATED,
            'old_data' => $oldData,
            'new_data' => $newData,
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userName,
            'description' => "{$userName} updated task #{$taskId}",
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
        $userName = $userInfo['user_name'] ?? 'System';
        
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_DELETED,
            'old_data' => $taskData,
            'new_data' => [],
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userName,
            'description' => "{$userName} deleted task #{$taskId}",
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
        $userName = $userInfo['user_name'] ?? 'System';
        
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_RESTORED,
            'old_data' => [],
            'new_data' => $taskData,
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userName,
            'description' => "{$userName} restored task #{$taskId}",
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
        $userName = $userInfo['user_name'] ?? 'System';
        
        return self::create([
            'task_id' => $taskId,
            'action' => self::ACTION_FORCE_DELETED,
            'old_data' => $taskData,
            'new_data' => [],
            'user_id' => $userInfo['user_id'] ?? null,
            'user_name' => $userName,
            'description' => "{$userName} permanently deleted task #{$taskId}",
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

    /**
     * Convert the log to an array formatted for API responses
     *
     * @param array $options
     * @return array
     */
    public function toResponseArray(array $options = []): array
    {
        $includeMetadata = $options['include_metadata'] ?? true;
        $dateFormat = $options['date_format'] ?? 'iso8601';
        $includeChanges = $options['include_changes'] ?? true;

        $response = [
            'id' => (string) $this->_id,
            'task_id' => (int) $this->task_id,
            'action' => $this->action,
            'action_display' => $this->getActionDisplayName(),
            'user_id' => $this->user_id ? (int) $this->user_id : null,
            'user_name' => $this->user_name ?? 'System',
            'description' => $this->description ?? $this->getFormattedMessageAttribute(),
            'created_at' => $this->formatDate($this->created_at, $dateFormat),
            'updated_at' => $this->formatDate($this->updated_at, $dateFormat)
        ];

        // Add data fields if they exist and are relevant
        if ($this->shouldIncludeOldData()) {
            $response['old_data'] = $this->formatDataForResponse($this->old_data);
        }

        if ($this->shouldIncludeNewData()) {
            $response['new_data'] = $this->formatDataForResponse($this->new_data);
        }

        // Add change summary for updates
        if ($includeChanges && $this->action === self::ACTION_UPDATED) {
            $response['changes'] = $this->getChangesSummary();
        }

        // Add metadata if requested
        if ($includeMetadata) {
            $response['meta'] = $this->getResponseMetadata($options);
        }

        return $response;
    }

    /**
     * Get action display name
     *
     * @return string
     */
    public function getActionDisplayName(): string
    {
        $displayNames = [
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_DELETED => 'Deleted',
            self::ACTION_RESTORED => 'Restored',
            self::ACTION_FORCE_DELETED => 'Permanently Deleted'
        ];

        return $displayNames[$this->action] ?? ucfirst($this->action);
    }

    /**
     * Format date for response
     *
     * @param mixed $date
     * @param string $format
     * @return string|null
     */
    protected function formatDate($date, string $format = 'iso8601'): ?string
    {
        if (!$date) {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        switch ($format) {
            case 'iso8601':
                return $carbon->toISOString();
            case 'timestamp':
                return (string) $carbon->timestamp;
            case 'human':
                return $carbon->diffForHumans();
            case 'date_only':
                return $carbon->toDateString();
            case 'datetime':
                return $carbon->toDateTimeString();
            default:
                return $carbon->format($format);
        }
    }

    /**
     * Format data (old_data/new_data) for response
     *
     * @param mixed $data
     * @return array|null
     */
    protected function formatDataForResponse($data): ?array
    {
        if (empty($data)) {
            return null;
        }

        if (is_array($data)) {
            return $data;
        }

        if (is_object($data)) {
            return json_decode(json_encode($data), true);
        }

        return ['raw' => $data];
    }

    /**
     * Check if old_data should be included in response
     *
     * @return bool
     */
    protected function shouldIncludeOldData(): bool
    {
        return !empty($this->old_data) && in_array($this->action, [
            self::ACTION_UPDATED,
            self::ACTION_DELETED,
            self::ACTION_FORCE_DELETED
        ]);
    }

    /**
     * Check if new_data should be included in response
     *
     * @return bool
     */
    protected function shouldIncludeNewData(): bool
    {
        return !empty($this->new_data) && in_array($this->action, [
            self::ACTION_CREATED,
            self::ACTION_UPDATED,
            self::ACTION_RESTORED
        ]);
    }

    /**
     * Get changes summary for update actions
     *
     * @return array|null
     */
    public function getChangesSummary(): ?array
    {
        if ($this->action !== self::ACTION_UPDATED || empty($this->old_data) || empty($this->new_data)) {
            return null;
        }

        $oldData = is_array($this->old_data) ? $this->old_data : [];
        $newData = is_array($this->new_data) ? $this->new_data : [];
        
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));

        foreach ($allKeys as $key) {
            $oldValue = $oldData[$key] ?? null;
            $newValue = $newData[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $key,
                    'from' => $oldValue,
                    'to' => $newValue,
                    'change_type' => $this->getChangeType($oldValue, $newValue)
                ];
            }
        }

        return empty($changes) ? null : [
            'total_changes' => count($changes),
            'fields_changed' => array_column($changes, 'field'),
            'details' => $changes
        ];
    }

    /**
     * Get response metadata
     *
     * @param array $options
     * @return array
     */
    protected function getResponseMetadata(array $options = []): array
    {
        $meta = [
            'log_age' => $this->created_at->diffForHumans(),
            'has_data_changes' => $this->hasDataChanges(),
            'is_system_action' => $this->isSystemAction(),
            'formatted_message' => $this->getFormattedMessageAttribute()
        ];

        // Add technical metadata if requested
        if ($options['include_technical'] ?? false) {
            $meta['technical'] = [
                'collection' => $this->getTable(),
                'mongo_id' => (string) ($this->_id ?? $this->getKey()),
                'document_size' => $this->estimateDocumentSize()
            ];
        }

        return $meta;
    }

    /**
     * Check if the log has data changes
     *
     * @return bool
     */
    public function hasDataChanges(): bool
    {
        return !empty($this->old_data) && !empty($this->new_data);
    }

    /**
     * Check if this is a system action (no user_id)
     *
     * @return bool
     */
    public function isSystemAction(): bool
    {
        return empty($this->user_id) || $this->user_name === 'System';
    }

    /**
     * Get the type of change between two values
     *
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return string
     */
    protected function getChangeType($oldValue, $newValue): string
    {
        if ($oldValue === null && $newValue !== null) {
            return 'added';
        }
        
        if ($oldValue !== null && $newValue === null) {
            return 'removed';
        }
        
        return 'modified';
    }

    /**
     * Estimate document size
     *
     * @return string
     */
    protected function estimateDocumentSize(): string
    {
        $size = strlen(json_encode($this->toArray()));
        
        if ($size > 1024) {
            return round($size / 1024, 2) . ' KB';
        }
        
        return $size . ' bytes';
    }

    /**
     * Convert collection to minimal response format
     *
     * @param \Illuminate\Support\Collection $logs
     * @param array $options
     * @return array
     */
    public static function toResponseCollection($logs, array $options = []): array
    {
        $includeCollectionMeta = $options['include_collection_meta'] ?? true;
        
        $formattedLogs = $logs->map(function ($log) use ($options) {
            return $log->toResponseArray(array_merge($options, ['include_metadata' => false]));
        });

        if (!$includeCollectionMeta) {
            return $formattedLogs->toArray();
        }

        // Add collection-level metadata
        $meta = [
            'total_logs' => $logs->count(),
            'action_distribution' => $logs->groupBy('action')->map->count()->toArray(),
            'date_range' => [
                'oldest' => $logs->min('created_at')?->toISOString(),
                'newest' => $logs->max('created_at')?->toISOString()
            ],
            'has_user_actions' => $logs->where('user_id', '!=', null)->count() > 0,
            'has_system_actions' => $logs->where('user_id', null)->count() > 0
        ];

        return [
            'logs' => $formattedLogs->toArray(),
            'meta' => $meta
        ];
    }
}