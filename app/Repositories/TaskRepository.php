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
     */
    public function create(array $data): Task
    {
        return Task::create($data);
    }

    /**
     * Update an existing task
     *
     * @param int $id
     * @param array $data
     * @return Task|null
     */
    public function update(int $id, array $data): ?Task
    {
        $task = $this->findById($id);
        
        if (!$task) {
            return null;
        }
        
        $task->update($data);
        
        return $task->fresh();
    }

    /**
     * Delete a task (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $task = $this->findById($id);
        
        if (!$task) {
            return false;
        }
        
        return $task->delete();
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
}