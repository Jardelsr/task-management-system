<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when task operations fail
 */
class TaskOperationException extends Exception
{
    /**
     * The operation that failed
     *
     * @var string|null
     */
    protected ?string $operation;

    /**
     * The task ID involved in the operation
     *
     * @var int|null
     */
    protected ?int $taskId;

    /**
     * Create a new TaskOperationException instance
     *
     * @param string $message
     * @param string|null $operation
     * @param int|null $taskId
     * @param int $code
     */
    public function __construct(
        string $message = 'Task operation failed',
        ?string $operation = null,
        ?int $taskId = null,
        int $code = 500
    ) {
        $this->operation = $operation;
        $this->taskId = $taskId;
        
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
     * Get the error details for API response
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return [
            'error' => 'Task operation failed',
            'message' => $this->getMessage(),
            'operation' => $this->operation,
            'task_id' => $this->taskId,
            'code' => 'TASK_OPERATION_FAILED'
        ];
    }
}