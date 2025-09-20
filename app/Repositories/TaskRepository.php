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
}