<?php

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

interface TaskRepositoryInterface
{
    /**
     * Find all tasks, optionally filtered by status
     *
     * @param string|null $status
     * @return Collection<int, Task>
     */
    public function findAll(?string $status = null): Collection;

    /**
     * Find a task by ID
     *
     * @param int $id
     * @return Task|null
     */
    public function findById(int $id): ?Task;

    /**
     * Create a new task
     *
     * @param array $data
     * @return Task
     */
    public function create(array $data): Task;

    /**
     * Update an existing task
     *
     * @param int $id
     * @param array $data
     * @return Task|null
     */
    public function update(int $id, array $data): ?Task;

    /**
     * Delete a task (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Find tasks by status
     *
     * @param string $status
     * @return Collection<int, Task>
     */
    public function findByStatus(string $status): Collection;

    /**
     * Find overdue tasks
     *
     * @return Collection<int, Task>
     */
    public function findOverdue(): Collection;

    /**
     * Find tasks with due date
     *
     * @return Collection<int, Task>
     */
    public function findWithDueDate(): Collection;

    /**
     * Count tasks by status
     *
     * @param string|null $status
     * @return int
     */
    public function countByStatus(?string $status = null): int;

    /**
     * Restore a soft-deleted task
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Force delete a task (permanent deletion)
     *
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool;

    /**
     * Find tasks with advanced filtering
     *
     * @param array $filters
     * @return Collection<int, Task>
     */
    public function findWithFilters(array $filters): Collection;

    /**
     * Count tasks with advanced filtering
     *
     * @param array $filters
     * @return int
     */
    public function countWithFilters(array $filters): int;
}