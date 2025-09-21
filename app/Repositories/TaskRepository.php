<?php

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use App\Services\DatabaseConnectionService;
use App\Services\SqlInjectionProtectionService;

class TaskRepository implements TaskRepositoryInterface
{
    private SqlInjectionProtectionService $sqlProtectionService;

    public function __construct(SqlInjectionProtectionService $sqlProtectionService)
    {
        $this->sqlProtectionService = $sqlProtectionService;
    }
    /**
     * Find all tasks, optionally filtered by status
     *
     * @param string|null $status
     * @return Collection<int, Task>
     */
    public function findAll(?string $status = null): Collection
    {
        $query = Task::query();
        
        if ($status !== null) {
            // Sanitize status input for SQL injection protection
            $status = $this->sqlProtectionService->sanitizeInput($status, 'task_status_filter');
            $query->byStatus($status);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Find a task by ID
     *
     * @param int $id
     * @return Task|null
     */
    public function findById(int $id): ?Task
    {
        return Task::find($id);
    }

    /**
     * Find a task by ID or throw an exception if not found
     *
     * @param int $id
     * @return Task
     * @throws \App\Exceptions\TaskNotFoundException
     */
    public function findByIdOrFail(int $id): Task
    {
        $task = Task::find($id);
        
        if (!$task) {
            throw new \App\Exceptions\TaskNotFoundException($id, 'find');
        }
        
        return $task;
    }

    /**
     * Create a new task with enhanced logging and validation
     *
     * @param array $data
     * @return Task
     * @throws \App\Exceptions\DatabaseException
     * @throws \App\Exceptions\TaskOperationException
     */
    public function create(array $data): Task
    {
        return DatabaseConnectionService::executeWithRetry(
            function() use ($data) {
                // Pre-creation hooks
                $this->beforeTaskCreation($data);
                
                // Apply defaults and sanitization
                $preparedData = $this->prepareTaskData($data);
                
                // Create the task
                $task = Task::create($preparedData);
                
                // Post-creation hooks
                $this->afterTaskCreation($task, $preparedData);
                
                return $task;
            },
            'create_task',
            'mysql'
        );
    }

    /**
     * Update an existing task
     *
     * @param int $id
     * @param array $data
     * @return Task|null
     * @throws \App\Exceptions\DatabaseException
     * @throws \App\Exceptions\TaskOperationException
     * @throws \App\Exceptions\TaskNotFoundException
     */
    public function update(int $id, array $data): ?Task
    {
        try {
            $task = $this->findById($id);
            
            if (!$task) {
                throw new \App\Exceptions\TaskNotFoundException($id, 'update', null, ['requested_data' => $data]);
            }

            // Store original data for logging
            $originalData = $task->toArray();
            
            // Log the update attempt with request details
            $this->logUpdateAttempt($id, $data, $data, true, null, [
                'original_task_status' => $task->status,
                'original_task_priority' => $task->priority,
                'has_assignment' => $task->assigned_to !== null,
            ]);
            
            // Filter out empty/null values for partial updates
            $updateData = array_filter($data, function($value) {
                return $value !== '' && $value !== null;
            });

            // Handle null values explicitly if they were intentionally set
            foreach ($data as $key => $value) {
                if ($value === null && array_key_exists($key, $data)) {
                    $updateData[$key] = null;
                }
            }

            // Only proceed if there's data to update
            if (empty($updateData)) {
                return $task;
            }
            
            $task->update($updateData);
            $updatedTask = $task->fresh();

            // Verify the task still exists after update (in case of race conditions)
            if (!$updatedTask) {
                throw new \App\Exceptions\TaskOperationException(
                    'Task was deleted during update operation',
                    'update',
                    $id
                );
            }

            // Log the update operation
            $this->logTaskUpdate($id, $originalData, $updatedTask->toArray(), $updateData);
            
            return $updatedTask;
        } catch (\Illuminate\Database\QueryException $e) {
            throw new \App\Exceptions\DatabaseException(
                'Failed to update task: ' . $e->getMessage(),
                'update',
                ['id' => $id, 'data' => $data],
                500
            );
        } catch (\App\Exceptions\TaskOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \App\Exceptions\TaskOperationException(
                'Unexpected error during task update: ' . $e->getMessage(),
                'update',
                $id,
                500
            );
        }
    }

    /**
     * Delete a task (soft delete)
     *
     * @param int $id
     * @return bool
     * @throws \App\Exceptions\DatabaseException
     * @throws \App\Exceptions\TaskNotFoundException
     */
    public function delete(int $id): bool
    {
        try {
            $task = $this->findById($id);
            
            if (!$task) {
                throw new \App\Exceptions\TaskNotFoundException($id, 'delete');
            }
            
            // Log the delete operation before deleting
            $this->logTaskDelete($id, $task->toArray());
            
            return $task->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            throw new \App\Exceptions\DatabaseException(
                'Failed to delete task: ' . $e->getMessage(),
                'delete',
                ['id' => $id],
                500
            );
        } catch (\Exception $e) {
            throw new \App\Exceptions\TaskOperationException(
                'Unexpected error during task deletion: ' . $e->getMessage(),
                'delete',
                $id,
                500
            );
        }
    }

    /**
     * Find tasks by status
     *
     * @param string $status
     * @return Collection<int, Task>
     */
    public function findByStatus(string $status): Collection
    {
        // Sanitize status input for SQL injection protection
        $status = $this->sqlProtectionService->sanitizeInput($status, 'task_status_lookup');
        
        return Task::byStatus($status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find overdue tasks
     *
     * @return Collection<int, Task>
     */
    public function findOverdue(): Collection
    {
        return Task::overdue()
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Find tasks with due date
     *
     * @return Collection<int, Task>
     */
    public function findWithDueDate(): Collection
    {
        return Task::withDueDate()
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Count tasks by status
     *
     * @param string|null $status
     * @return int
     */
    public function countByStatus(?string $status = null): int
    {
        $query = Task::query();
        
        if ($status !== null) {
            // Sanitize status input for SQL injection protection
            $status = $this->sqlProtectionService->sanitizeInput($status, 'task_status_count');
            $query->byStatus($status);
        }
        
        return $query->count();
    }

    /**
     * Restore a soft-deleted task
     *
     * @param int $id
     * @return bool
     * @throws \App\Exceptions\TaskNotFoundException
     * @throws \App\Exceptions\TaskRestoreException
     */
    public function restore(int $id): bool
    {
        try {
            $task = Task::withTrashed()->find($id);
            
            if (!$task) {
                throw new \App\Exceptions\TaskNotFoundException($id, 'restore');
            }
            
            if (!$task->trashed()) {
                throw new \App\Exceptions\TaskRestoreException(
                    'Task is not deleted and cannot be restored',
                    'restore', 
                    $id, 
                    'Task is currently active'
                );
            }
            
            $restored = $task->restore();
            
            if ($restored) {
                // Log the restore operation
                $this->logTaskRestore($id, $task->toArray());
            }
            
            return $restored;
        } catch (\Exception $e) {
            throw new \App\Exceptions\TaskOperationException(
                'Failed to restore task: ' . $e->getMessage(),
                'restore',
                $id,
                500
            );
        }
    }

    /**
     * Force delete a task (permanent deletion)
     *
     * @param int $id
     * @return bool
     * @throws \App\Exceptions\TaskNotFoundException
     */
    public function forceDelete(int $id): bool
    {
        try {
            $task = Task::withTrashed()->find($id);
            
            if (!$task) {
                throw new \App\Exceptions\TaskNotFoundException($id, 'force_delete');
            }
            
            // Log the force delete operation before deleting
            $this->logTaskForceDelete($id, $task->toArray());
            
            return $task->forceDelete();
        } catch (\Exception $e) {
            throw new \App\Exceptions\TaskOperationException(
                'Failed to force delete task: ' . $e->getMessage(),
                'force_delete',
                $id,
                500
            );
        }
    }

    /**
     * Find only trashed (soft-deleted) tasks
     *
     * @return Collection<int, Task>
     */
    public function findTrashed(): Collection
    {
        return Task::onlyTrashed()->get();
    }

    /**
     * Find trashed task by ID
     *
     * @param int $id
     * @return Task|null
     */
    public function findTrashedById(int $id): ?Task
    {
        return Task::onlyTrashed()->find($id);
    }

    /**
     * Find tasks including trashed ones
     *
     * @return Collection<int, Task>
     */
    public function findWithTrashed(): Collection
    {
        return Task::withTrashed()->get();
    }

    /**
     * Find tasks with advanced filtering
     *
     * @param array $filters
     * @return Collection<int, Task>
     */
    public function findWithFilters(array $filters): Collection
    {
        $query = Task::query();

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Apply assigned_to filter
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        // Apply created_by filter
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Apply overdue filter
        if (!empty($filters['overdue']) && $filters['overdue'] === 'true') {
            $query->overdue();
        }

        // Apply with_due_date filter
        if (!empty($filters['with_due_date']) && $filters['with_due_date'] === 'true') {
            $query->withDueDate();
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Apply pagination
        if (isset($filters['limit']) && isset($filters['offset'])) {
            $query->skip($filters['offset'])->take($filters['limit']);
        }

        return $query->get();
    }

    /**
     * Count tasks with advanced filtering
     *
     * @param array $filters
     * @return int
     */
    public function countWithFilters(array $filters): int
    {
        $query = Task::query();

        // Apply same filters as findWithFilters but without pagination
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (!empty($filters['overdue']) && $filters['overdue'] === 'true') {
            $query->overdue();
        }

        if (!empty($filters['with_due_date']) && $filters['with_due_date'] === 'true') {
            $query->withDueDate();
        }

        return $query->count();
    }

    /**
     * Enhanced logging for task update operations
     *
     * @param int $taskId
     * @param array $originalData
     * @param array $updatedData
     * @param array $changedFields
     * @return void
     */
    private function logTaskUpdate(int $taskId, array $originalData, array $updatedData, array $changedFields): void
    {
        try {
            // Build detailed change analysis
            $changes = [];
            $significantChanges = [];
            
            foreach ($changedFields as $field => $newValue) {
                $oldValue = $originalData[$field] ?? null;
                if ($oldValue !== $newValue) {
                    $changeData = [
                        'old' => $oldValue,
                        'new' => $newValue,
                        'type' => $this->getChangeTypeForField($oldValue, $newValue),
                        'field_category' => $this->getFieldCategory($field),
                    ];
                    
                    $changes[$field] = $changeData;
                    
                    // Track significant changes
                    if ($this->isSignificantChange($field, $oldValue, $newValue)) {
                        $significantChanges[] = $field;
                    }
                }
            }

            if (!empty($changes)) {
                // Log with enhanced metadata
                \App\Models\TaskLog::create([
                    'task_id' => $taskId,
                    'action' => \App\Models\TaskLog::ACTION_UPDATED,
                    'old_data' => $originalData,
                    'new_data' => $updatedData,
                    'user_id' => 1, // Default to system user
                    'metadata' => [
                        'repository_operation' => 'update',
                        'changed_fields' => array_keys($changes),
                        'field_changes' => $changes,
                        'significant_changes' => $significantChanges,
                        'change_summary' => $this->generateUpdateSummary($changes),
                        'update_context' => [
                            'total_fields_changed' => count($changes),
                            'has_status_change' => isset($changes['status']),
                            'has_priority_change' => isset($changes['priority']),
                            'has_assignment_change' => isset($changes['assigned_to']),
                            'has_due_date_change' => isset($changes['due_date']),
                        ],
                        'performance_metrics' => [
                            'update_timestamp' => Carbon::now()->toISOString(),
                            'processing_node' => gethostname(),
                        ]
                    ],
                    'description' => $this->generateRepositoryUpdateDescription($changes),
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent() ?? 'System/Repository',
                    'created_at' => Carbon::now(),
                ]);
            }
        } catch (\Exception $e) {
            // Enhanced error logging
            \Log::error('Failed to log task update from repository', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'original_data_keys' => array_keys($originalData),
                'updated_data_keys' => array_keys($updatedData),
                'changed_fields' => array_keys($changedFields),
            ]);
        }
    }

    /**
     * Determine change type for a specific field
     *
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return string
     */
    private function getChangeTypeForField($oldValue, $newValue): string
    {
        if ($oldValue === null && $newValue !== null) {
            return 'added';
        }
        if ($oldValue !== null && $newValue === null) {
            return 'removed';
        }
        if ($oldValue !== $newValue) {
            return 'modified';
        }
        return 'unchanged';
    }

    /**
     * Categorize field for better organization
     *
     * @param string $field
     * @return string
     */
    private function getFieldCategory(string $field): string
    {
        $categories = [
            'title' => 'content',
            'description' => 'content',
            'status' => 'workflow',
            'priority' => 'workflow',
            'assigned_to' => 'assignment',
            'due_date' => 'scheduling',
            'created_by' => 'metadata',
            'created_at' => 'metadata',
            'updated_at' => 'metadata',
        ];

        return $categories[$field] ?? 'other';
    }

    /**
     * Determine if a field change is significant
     *
     * @param string $field
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return bool
     */
    private function isSignificantChange(string $field, $oldValue, $newValue): bool
    {
        $significantFields = ['status', 'priority', 'assigned_to', 'due_date', 'title'];
        
        if (!in_array($field, $significantFields)) {
            return false;
        }

        // Special cases for specific fields
        switch ($field) {
            case 'status':
                return $newValue === Task::STATUS_COMPLETED || $oldValue === Task::STATUS_COMPLETED;
            case 'priority':
                $priorities = ['low' => 1, 'medium' => 2, 'high' => 3, 'urgent' => 4];
                return abs(($priorities[$newValue] ?? 2) - ($priorities[$oldValue] ?? 2)) > 1;
            case 'assigned_to':
                return $oldValue === null || $newValue === null; // Assignment or unassignment
            default:
                return true;
        }
    }

    /**
     * Generate a summary of the update changes
     *
     * @param array $changes
     * @return string
     */
    private function generateUpdateSummary(array $changes): string
    {
        $summaries = [];
        
        foreach ($changes as $field => $change) {
            switch ($field) {
                case 'status':
                    $summaries[] = "Status: {$change['old']} → {$change['new']}";
                    break;
                case 'priority':
                    $summaries[] = "Priority: {$change['old']} → {$change['new']}";
                    break;
                case 'assigned_to':
                    $oldUser = $change['old'] ? "user {$change['old']}" : 'unassigned';
                    $newUser = $change['new'] ? "user {$change['new']}" : 'unassigned';
                    $summaries[] = "Assignment: {$oldUser} → {$newUser}";
                    break;
                case 'title':
                    $summaries[] = "Title updated";
                    break;
                case 'due_date':
                    $summaries[] = "Due date updated";
                    break;
                default:
                    $summaries[] = ucfirst($field) . " updated";
            }
        }

        return implode(', ', $summaries);
    }

    /**
     * Generate repository-level update description
     *
     * @param array $changes
     * @return string
     */
    private function generateRepositoryUpdateDescription(array $changes): string
    {
        $fieldCount = count($changes);
        $fieldNames = array_keys($changes);
        
        if ($fieldCount === 1) {
            return "Repository: Updated {$fieldNames[0]} field";
        } elseif ($fieldCount <= 3) {
            return "Repository: Updated " . implode(', ', $fieldNames) . " fields";
        } else {
            return "Repository: Updated {$fieldCount} fields (" . implode(', ', array_slice($fieldNames, 0, 3)) . "...)";
        }
    }

    /**
     * Log update attempts (including failed ones) for debugging and audit purposes
     *
     * @param int $taskId
     * @param array $requestData
     * @param array $validatedData
     * @param bool $success
     * @param string|null $errorMessage
     * @param array $context
     * @return void
     */
    public function logUpdateAttempt(
        int $taskId, 
        array $requestData, 
        array $validatedData, 
        bool $success = true, 
        ?string $errorMessage = null,
        array $context = []
    ): void {
        try {
            \App\Models\TaskLog::create([
                'task_id' => $taskId,
                'action' => 'update_attempt',
                'old_data' => [],
                'new_data' => [],
                'user_id' => 1,
                'metadata' => [
                    'attempt_status' => $success ? 'success' : 'failed',
                    'request_data' => $requestData,
                    'validated_data' => $validatedData,
                    'error_message' => $errorMessage,
                    'attempt_context' => $context,
                    'performance_metrics' => [
                        'attempt_timestamp' => Carbon::now()->toISOString(),
                        'processing_node' => gethostname(),
                        'memory_usage' => memory_get_usage(true),
                        'memory_peak' => memory_get_peak_usage(true),
                    ],
                    'request_analysis' => [
                        'fields_requested' => array_keys($requestData),
                        'fields_validated' => array_keys($validatedData),
                        'fields_filtered_out' => array_diff(array_keys($requestData), array_keys($validatedData)),
                        'has_partial_data' => count($requestData) !== count($validatedData),
                    ]
                ],
                'description' => $success 
                    ? "Update attempt successful for task {$taskId}" 
                    : "Update attempt failed for task {$taskId}: {$errorMessage}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System/Repository',
                'created_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to log update attempt', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'attempt_success' => $success,
                'original_error' => $errorMessage,
            ]);
        }
    }

    /**
     * Log field-level changes for detailed audit trails
     *
     * @param int $taskId
     * @param string $fieldName
     * @param mixed $oldValue
     * @param mixed $newValue
     * @param array $metadata
     * @return void
     */
    public function logFieldChange(int $taskId, string $fieldName, $oldValue, $newValue, array $metadata = []): void
    {
        try {
            \App\Models\TaskLog::create([
                'task_id' => $taskId,
                'action' => 'field_change',
                'old_data' => [$fieldName => $oldValue],
                'new_data' => [$fieldName => $newValue],
                'user_id' => 1,
                'metadata' => array_merge([
                    'field_name' => $fieldName,
                    'change_type' => $this->getChangeTypeForField($oldValue, $newValue),
                    'field_category' => $this->getFieldCategory($fieldName),
                    'is_significant' => $this->isSignificantChange($fieldName, $oldValue, $newValue),
                    'change_timestamp' => Carbon::now()->toISOString(),
                ], $metadata),
                'description' => "Field '{$fieldName}' changed from '" . json_encode($oldValue) . "' to '" . json_encode($newValue) . "'",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System/Repository',
                'created_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to log field change', [
                'task_id' => $taskId,
                'field_name' => $fieldName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log task delete operation
     *
     * @param int $taskId
     * @param array $taskData
     * @return void
     */
    private function logTaskDelete(int $taskId, array $taskData): void
    {
        try {
            \App\Models\TaskLog::logDeleted($taskId, $taskData);
        } catch (\Exception $e) {
            // Log the logging error but don't fail the delete
            \Log::error('Failed to log task deletion', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log task restore operation
     *
     * @param int $taskId
     * @param array $taskData
     * @return void
     */
    private function logTaskRestore(int $taskId, array $taskData): void
    {
        try {
            \App\Models\TaskLog::logRestored($taskId, $taskData);
        } catch (\Exception $e) {
            \Log::error('Failed to log task restore', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log task force delete operation
     *
     * @param int $taskId
     * @param array $taskData
     * @return void
     */
    private function logTaskForceDelete(int $taskId, array $taskData): void
    {
        try {
            \App\Models\TaskLog::logForceDeleted($taskId, $taskData);
        } catch (\Exception $e) {
            \Log::error('Failed to log task force delete', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log task creation operation
     *
     * @param int $taskId
     * @param array $taskData
     * @return void
     */
    private function logTaskCreate(int $taskId, array $taskData): void
    {
        try {
            \App\Models\TaskLog::logCreated($taskId, $taskData);
        } catch (\Exception $e) {
            \Log::error('Failed to log task creation', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Hook executed before task creation
     *
     * @param array $data
     * @return void
     */
    private function beforeTaskCreation(array $data): void
    {
        // Log the creation attempt
        \Log::info('Task creation initiated', [
            'data' => $data,
            'timestamp' => Carbon::now()->toISOString(),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent()
        ]);
    }

    /**
     * Prepare and sanitize task data before creation
     *
     * @param array $data
     * @return array
     */
    private function prepareTaskData(array $data): array
    {
        // Apply default values
        if (!isset($data['status'])) {
            $data['status'] = Task::STATUS_PENDING;
        }

        if (!isset($data['priority'])) {
            $data['priority'] = 'medium';
        }

        // Sanitize text fields
        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
        }

        if (isset($data['description'])) {
            $data['description'] = trim($data['description']);
        }

        // Ensure due_date is properly formatted
        if (isset($data['due_date']) && $data['due_date']) {
            try {
                $data['due_date'] = \Carbon\Carbon::parse($data['due_date'])->toDateTimeString();
            } catch (\Exception $e) {
                \Log::warning('Invalid due_date format in task creation', [
                    'original_due_date' => $data['due_date'],
                    'error' => $e->getMessage()
                ]);
                unset($data['due_date']);
            }
        }

        return $data;
    }

    /**
     * Hook executed after successful task creation
     *
     * @param Task $task
     * @param array $originalData
     * @return void
     */
    private function afterTaskCreation(Task $task, array $originalData): void
    {
        // Log to MongoDB for audit trail
        $this->logTaskCreate($task->id, $task->toArray());
        
        // Log successful creation
        \Log::info('Task created successfully', [
            'task_id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'assigned_to' => $task->assigned_to,
            'due_date' => $task->due_date?->toISOString(),
            'created_at' => $task->created_at->toISOString(),
            'original_input' => $originalData
        ]);

        // Check for special conditions and log them
        $this->logSpecialCreationConditions($task);
    }

    /**
     * Log special conditions during task creation
     *
     * @param Task $task
     * @return void
     */
    private function logSpecialCreationConditions(Task $task): void
    {
        $conditions = [];

        // Check if task is created as overdue
        if ($task->due_date && $task->due_date < Carbon::now()) {
            $conditions[] = 'overdue_on_creation';
        }

        // Check for high priority tasks
        if (in_array($task->priority, ['high', 'urgent'])) {
            $conditions[] = 'high_priority';
        }

        // Check for immediate assignment
        if ($task->assigned_to) {
            $conditions[] = 'assigned_on_creation';
        }

        // Check for weekend/holiday creation (if applicable)
        if (Carbon::now()->isWeekend()) {
            $conditions[] = 'weekend_creation';
        }

        if (!empty($conditions)) {
            \Log::info('Special task creation conditions detected', [
                'task_id' => $task->id,
                'conditions' => $conditions,
                'details' => [
                    'due_date' => $task->due_date?->toISOString(),
                    'priority' => $task->priority,
                    'assigned_to' => $task->assigned_to,
                    'created_on_weekend' => Carbon::now()->isWeekend(),
                    'created_at' => Carbon::now()->toISOString()
                ]
            ]);
        }
    }

    /**
     * Log database errors during task operations
     *
     * @param string $operation
     * @param \Illuminate\Database\QueryException $exception
     * @param array $context
     * @return void
     */
    private function logDatabaseError(string $operation, \Illuminate\Database\QueryException $exception, array $context = []): void
    {
        \Log::error("Database error during task {$operation}", [
            'operation' => $operation,
            'error_code' => $exception->getCode(),
            'error_message' => $exception->getMessage(),
            'sql_state' => $exception->errorInfo[0] ?? null,
            'mysql_error_code' => $exception->errorInfo[1] ?? null,
            'mysql_error_message' => $exception->errorInfo[2] ?? null,
            'context' => $context,
            'timestamp' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Log unexpected errors during task operations
     *
     * @param string $operation
     * @param \Exception $exception
     * @param array $context
     * @return void
     */
    private function logUnexpectedError(string $operation, \Exception $exception, array $context = []): void
    {
        \Log::error("Unexpected error during task {$operation}", [
            'operation' => $operation,
            'exception_class' => get_class($exception),
            'error_message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'timestamp' => Carbon::now()->toISOString()
        ]);
    }
}
