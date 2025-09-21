<?php

namespace App\Services;

use App\Models\Task;
use App\Services\ValidationMessageService;
use App\Exceptions\TaskValidationException;
use Carbon\Carbon;

/**
 * Business Rules Validation Service
 * 
 * This service handles complex business logic validation that goes beyond
 * simple field validation rules.
 */
class BusinessRulesValidator
{
    /**
     * Valid status transitions
     *
     * @var array
     */
    private static array $validStatusTransitions = [
        'pending' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'pending', 'cancelled'],
        'completed' => [], // Completed tasks cannot transition to other statuses
        'cancelled' => ['pending'] // Cancelled tasks can only be reopened to pending
    ];

    /**
     * Validate status transition
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @param array $context Additional context data
     * @throws TaskValidationException
     */
    public static function validateStatusTransition(string $currentStatus, string $newStatus, array $context = []): void
    {
        if ($currentStatus === $newStatus) {
            return; // No transition needed
        }

        $allowedTransitions = self::$validStatusTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowedTransitions)) {
            throw new TaskValidationException(
                ['status' => [ValidationMessageService::getBusinessMessage('status_transition.invalid', [
                    'from' => $currentStatus,
                    'to' => $newStatus
                ])]],
                'status',
                'Invalid status transition'
            );
        }

        // Additional validation for specific transitions
        if ($newStatus === 'completed') {
            self::validateCompletionRequirements($context);
        }
    }

    /**
     * Validate requirements for marking task as completed
     *
     * @param array $context
     * @throws TaskValidationException
     */
    private static function validateCompletionRequirements(array $context): void
    {
        // Check if completion date is provided when marking as completed
        if (empty($context['completed_at'])) {
            throw new TaskValidationException(
                ['completed_at' => [ValidationMessageService::getBusinessMessage('status_transition.completed_requires_completion_date')]],
                'completed_at',
                'Completion date required'
            );
        }
    }

    /**
     * Validate task assignment rules
     *
     * @param array $data Task data
     * @param Task|null $existingTask Existing task for updates
     * @throws TaskValidationException
     */
    public static function validateTaskAssignment(array $data, ?Task $existingTask = null): void
    {
        $createdBy = $data['created_by'] ?? ($existingTask->created_by ?? null);
        $assignedTo = $data['assigned_to'] ?? ($existingTask->assigned_to ?? null);

        // Prevent self-assignment in certain scenarios
        if ($createdBy && $assignedTo && $createdBy === $assignedTo) {
            throw new TaskValidationException(
                ['assigned_to' => [ValidationMessageService::getBusinessMessage('assignment.self_assignment')]],
                'assigned_to',
                'Invalid assignment'
            );
        }

        // Validate high priority task assignment
        $priority = $data['priority'] ?? ($existingTask->priority ?? 'medium');
        if ($priority === 'high' && isset($data['assigned_to']) && $existingTask && $existingTask->assigned_to !== $data['assigned_to']) {
            // In a real system, you would check manager approval here
            // For now, just demonstrate the concept
            if (!isset($data['manager_approval']) || !$data['manager_approval']) {
                throw new TaskValidationException(
                    ['priority' => [ValidationMessageService::getBusinessMessage('priority.escalation_required')]],
                    'priority',
                    'Manager approval required'
                );
            }
        }
    }

    /**
     * Validate due date business rules
     *
     * @param array $data Task data
     * @param Task|null $existingTask Existing task for updates
     * @throws TaskValidationException
     */
    public static function validateDueDateRules(array $data, ?Task $existingTask = null): void
    {
        if (!isset($data['due_date'])) {
            return;
        }

        $dueDate = Carbon::parse($data['due_date']);
        $now = Carbon::now();

        // Cannot set due date in the past for active tasks
        $status = $data['status'] ?? ($existingTask->status ?? 'pending');
        if ($dueDate->isPast() && in_array($status, ['pending', 'in_progress'])) {
            throw new TaskValidationException(
                ['due_date' => [ValidationMessageService::getBusinessMessage('due_date.past_due_update')]],
                'due_date',
                'Invalid due date'
            );
        }

        // Check for overdue completion scenario
        if (isset($data['status']) && $data['status'] === 'completed' && $dueDate->isPast()) {
            $completedAt = isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : $now;
            if ($completedAt->isAfter($dueDate)) {
                // Task was completed after due date - might need special handling
                if (!isset($data['acknowledge_overdue']) || !$data['acknowledge_overdue']) {
                    throw new TaskValidationException(
                        ['due_date' => [ValidationMessageService::getBusinessMessage('due_date.overdue_completion')]],
                        'due_date',
                        'Overdue completion requires acknowledgment'
                    );
                }
            }
        }
    }

    /**
     * Validate task deletion rules
     *
     * @param Task $task
     * @param array $context Additional context
     * @throws TaskValidationException
     */
    public static function validateTaskDeletion(Task $task, array $context = []): void
    {
        // Cannot delete completed tasks (should be archived instead)
        if ($task->status === 'completed') {
            throw new TaskValidationException(
                ['task' => [ValidationMessageService::getBusinessMessage('task_deletion.already_completed')]],
                'task',
                'Cannot delete completed task'
            );
        }

        // Check for task dependencies (in a real system)
        // This would query a task_dependencies table or similar
        if (isset($context['has_dependencies']) && $context['has_dependencies']) {
            throw new TaskValidationException(
                ['task' => [ValidationMessageService::getBusinessMessage('task_deletion.has_dependencies')]],
                'task',
                'Task has dependencies'
            );
        }
    }

    /**
     * Validate complete task data with all business rules
     *
     * @param array $data Task data
     * @param Task|null $existingTask Existing task for updates
     * @param array $context Additional validation context
     * @throws TaskValidationException
     */
    public static function validateCompleteTaskBusinessRules(array $data, ?Task $existingTask = null, array $context = []): void
    {
        $errors = [];

        try {
            // Validate status transitions
            if (isset($data['status']) && $existingTask) {
                self::validateStatusTransition($existingTask->status, $data['status'], $data);
            }
        } catch (TaskValidationException $e) {
            $errors = array_merge($errors, $e->getErrors());
        }

        try {
            // Validate assignment rules
            self::validateTaskAssignment($data, $existingTask);
        } catch (TaskValidationException $e) {
            $errors = array_merge($errors, $e->getErrors());
        }

        try {
            // Validate due date rules
            self::validateDueDateRules($data, $existingTask);
        } catch (TaskValidationException $e) {
            $errors = array_merge($errors, $e->getErrors());
        }

        // If there are any business rule violations, throw combined exception
        if (!empty($errors)) {
            throw new TaskValidationException($errors, null, 'Business rule validation failed');
        }
    }

    /**
     * Get all valid status transitions
     *
     * @return array
     */
    public static function getValidStatusTransitions(): array
    {
        return self::$validStatusTransitions;
    }

    /**
     * Get valid next statuses for current status
     *
     * @param string $currentStatus
     * @return array
     */
    public static function getValidNextStatuses(string $currentStatus): array
    {
        return self::$validStatusTransitions[$currentStatus] ?? [];
    }

    /**
     * Check if status transition is valid
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function isValidStatusTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true; // No transition needed
        }

        $allowedTransitions = self::$validStatusTransitions[$from] ?? [];
        return in_array($to, $allowedTransitions);
    }

    /**
     * Validate workflow rules for task creation
     *
     * @param array $data
     * @throws TaskValidationException
     */
    public static function validateTaskCreationWorkflow(array $data): void
    {
        // New tasks should start with appropriate status
        $status = $data['status'] ?? 'pending';
        if (!in_array($status, ['pending', 'in_progress'])) {
            throw new TaskValidationException(
                ['status' => ['New tasks can only be created with "pending" or "in_progress" status']],
                'status',
                'Invalid initial status'
            );
        }

        // If created as in_progress, must have assignee
        if ($status === 'in_progress' && empty($data['assigned_to'])) {
            throw new TaskValidationException(
                ['assigned_to' => ['Tasks created with "in_progress" status must have an assignee']],
                'assigned_to',
                'Assignee required for in-progress tasks'
            );
        }
    }
}