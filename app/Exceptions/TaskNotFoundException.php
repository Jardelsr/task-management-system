<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when a task is not found
 */
class TaskNotFoundException extends Exception
{
    /**
     * The task ID that was not found
     *
     * @var int|null
     */
    protected ?int $taskId;

    /**
     * Create a new TaskNotFoundException instance
     *
     * @param int|null $taskId
     * @param string $message
     */
    public function __construct(?int $taskId = null, string $message = 'Task not found')
    {
        $this->taskId = $taskId;
        
        if ($taskId) {
            $message = "Task with ID {$taskId} not found";
        }
        
        parent::__construct($message, 404);
    }

    /**
     * Get the task ID that was not found
     *
     * @return int|null
     */
    public function getTaskId(): ?int
    {
        return $this->taskId;
    }

    /**
     * Get the error details for API response
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return [
            'error' => 'Task not found',
            'message' => $this->getMessage(),
            'task_id' => $this->taskId,
            'code' => 'TASK_NOT_FOUND'
        ];
    }
}