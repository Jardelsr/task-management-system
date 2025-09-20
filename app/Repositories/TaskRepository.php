<?php

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository implements TaskRepositoryInterface
{
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
     * Create a new task
     *
     * @param array $data
     * @return Task
     * @throws \App\Exceptions\DatabaseException
     */
    public function create(array $data): Task
    {
        try {
            return Task::create($data);
        } catch (\Illuminate\Database\QueryException $e) {
            throw new \App\Exceptions\DatabaseException(
                'Failed to create task: ' . $e->getMessage(),
                'create',
                ['data' => $data],
                500
            );
        } catch (\Exception $e) {
            throw new \App\Exceptions\TaskOperationException(
                'Unexpected error during task creation: ' . $e->getMessage(),
                'create',
                null,
                500
            );
        }
    }

    /**
     * Update an existing task
     *
     * @param int $id
     * @param array $data
     * @return Task|null
     * @throws \App\Exceptions\DatabaseException
     * @throws \App\Exceptions\TaskOperationException
     */
    public function update(int $id, array $data): ?Task
    {
        try {
            $task = $this->findById($id);
            
            if (!$task) {
                return null;
            }

            // Store original data for logging
            $originalData = $task->toArray();
            
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
     */
    public function delete(int $id): bool
    {
        try {
            $task = $this->findById($id);
            
            if (!$task) {
                return false;
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
            $query->byStatus($status);
        }
        
        return $query->count();
    }

    /**
     * Restore a soft-deleted task
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool
    {
        $task = Task::withTrashed()->find($id);
        
        if (!$task || !$task->trashed()) {
            return false;
        }
        
        return $task->restore();
    }

    /**
     * Force delete a task (permanent deletion)
     *
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool
    {
        $task = Task::withTrashed()->find($id);
        
        if (!$task) {
            return false;
        }
        
        return $task->forceDelete();
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
     * Log task update operation
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
            // Only log if there are actual changes
            $changes = [];
            foreach ($changedFields as $field => $newValue) {
                $oldValue = $originalData[$field] ?? null;
                if ($oldValue !== $newValue) {
                    $changes[$field] = [
                        'old' => $oldValue,
                        'new' => $newValue
                    ];
                }
            }

            if (!empty($changes)) {
                \App\Models\TaskLog::logUpdated($taskId, $originalData, $updatedData);
            }
        } catch (\Exception $e) {
            // Log the logging error but don't fail the update
            \Log::error('Failed to log task update', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
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
}