<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when task restore operations fail
 */
class TaskRestoreException extends Exception
{
    /**
     * The restore operation that failed
     *
     * @var string|null
     */
    protected ?string $operation;

    /**
     * The task ID involved in the restore operation
     *
     * @var int|null
     */
    protected ?int $taskId;

    /**
     * The reason why the restore failed
     *
     * @var string|null
     */
    protected ?string $reason;

    /**
     * Create a new TaskRestoreException instance
     *
     * @param string $message
     * @param string|null $operation
     * @param int|null $taskId
     * @param string|null $reason
     * @param int $code
     */
    public function __construct(
        string $message = 'Task restore operation failed',
        ?string $operation = null,
        ?int $taskId = null,
        ?string $reason = null,
        int $code = 409
    ) {
        $this->operation = $operation;
        $this->taskId = $taskId;
        $this->reason = $reason;
        parent::__construct($message, $code);
    }

    /**
     * Get the operation that failed
     *
     * @return string|null
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Get the task ID involved in the operation
     *
     * @return int|null
     */
    public function getTaskId(): ?int
    {
        return $this->taskId;
    }

    /**
     * Get the reason for failure
     *
     * @return string|null
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Get error details for API response
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return [
            'operation' => $this->operation,
            'task_id' => $this->taskId,
            'reason' => $this->reason,
            'suggestions' => $this->getSuggestions(),
        ];
    }

    /**
     * Get suggestions based on the restore failure reason
     *
     * @return array
     */
    protected function getSuggestions(): array
    {
        $suggestions = [];

        if ($this->reason === 'not_in_trash') {
            $suggestions[] = 'Task is not in trash. Check if the task exists and is deleted.';
            $suggestions[] = 'Use GET /tasks/{id} to verify task status.';
        } elseif ($this->reason === 'already_restored') {
            $suggestions[] = 'Task is already active and does not need to be restored.';
        } elseif ($this->reason === 'not_found') {
            $suggestions[] = 'Task does not exist. Check the task ID.';
            $suggestions[] = 'Use GET /tasks/trashed to see available trashed tasks.';
        } else {
            $suggestions[] = 'Check task status and permissions.';
            $suggestions[] = 'Contact support if the problem persists.';
        }

        return $suggestions;
    }

    /**
     * Static factory for "not in trash" scenario
     *
     * @param int $taskId
     * @return static
     */
    public static function notInTrash(int $taskId): static
    {
        return new static(
            "Task {$taskId} is not in trash and cannot be restored",
            'restore',
            $taskId,
            'not_in_trash'
        );
    }

    /**
     * Static factory for "already restored" scenario
     *
     * @param int $taskId
     * @return static
     */
    public static function alreadyRestored(int $taskId): static
    {
        return new static(
            "Task {$taskId} is already active and does not need to be restored",
            'restore',
            $taskId,
            'already_restored'
        );
    }

    /**
     * Static factory for generic restore failure
     *
     * @param int $taskId
     * @param string $reason
     * @return static
     */
    public static function failedToRestore(int $taskId, string $reason = 'unknown'): static
    {
        return new static(
            "Failed to restore task {$taskId}: {$reason}",
            'restore',
            $taskId,
            $reason
        );
    }
}