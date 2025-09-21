<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
     * The operation that was being performed when the task was not found
     *
     * @var string|null
     */
    protected ?string $operation;

    /**
     * Additional context about the error
     *
     * @var array
     */
    protected array $context;

    /**
     * Create a new TaskNotFoundException instance
     *
     * @param int|null $taskId
     * @param string|null $operation
     * @param string|null $message
     * @param array $context
     */
    public function __construct(
        ?int $taskId = null, 
        ?string $operation = null, 
        ?string $message = null,
        array $context = []
    ) {
        $this->taskId = $taskId;
        $this->operation = $operation;
        $this->context = $context;
        
        // Generate appropriate message
        if ($message === null) {
            $message = $this->generateMessage();
        }
        
        // Log the error for debugging
        $this->logError();
        
        parent::__construct($message, 404);
    }

    /**
     * Generate an appropriate error message
     *
     * @return string
     */
    private function generateMessage(): string
    {
        if ($this->taskId) {
            $baseMessage = "Task with ID {$this->taskId} not found";
            
            if ($this->operation) {
                $baseMessage .= " during {$this->operation} operation";
            }
            
            return $baseMessage;
        }
        
        return 'Task not found';
    }

    /**
     * Log the error for debugging purposes
     */
    private function logError(): void
    {
        try {
            // Get request details safely for logging
            $request = null;
            try {
                $request = request();
            } catch (\Exception $e) {
                // Request may not be available in unit tests or CLI context
            }
            
            Log::warning('Task not found', [
                'task_id' => $this->taskId,
                'operation' => $this->operation,
                'context' => $this->context,
                'timestamp' => Carbon::now()->toISOString(),
                'user_ip' => $request ? $request->ip() : 'unknown',
                'user_agent' => $request ? $request->userAgent() : 'unknown'
            ]);
        } catch (\Exception $e) {
            // Logging may not be available in unit tests or some contexts
            // Fail silently to not break exception creation
        }
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
     * Get the operation that was being performed
     *
     * @return string|null
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Get additional context about the error
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the error details for API response
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        $details = [
            'success' => false,
            'error' => 'Task not found',
            'message' => $this->getMessage(),
            'code' => 'TASK_NOT_FOUND',
            'timestamp' => Carbon::now()->toISOString()
        ];

        // Add task ID if available
        if ($this->taskId) {
            $details['task_id'] = $this->taskId;
        }

        // Add operation context if available
        if ($this->operation) {
            $details['operation'] = $this->operation;
        }

        // Add helpful suggestions
        $details['suggestions'] = $this->getSuggestions();

        return $details;
    }

    /**
     * Get helpful suggestions for the user
     *
     * @return array
     */
    public function getSuggestions(): array
    {
        $suggestions = [
            'Verify the task ID is correct',
            'Check if the task was deleted',
            'Use GET /tasks to list all available tasks'
        ];

        if ($this->operation === 'update' || $this->operation === 'delete') {
            $suggestions[] = "Use GET /tasks/{$this->taskId} to verify the task exists before attempting to {$this->operation}";
        }

        return $suggestions;
    }

    /**
     * Create a TaskNotFoundException for a specific operation
     *
     * @param int $taskId
     * @param string $operation
     * @param array $context
     * @return self
     */
    public static function forOperation(int $taskId, string $operation, array $context = []): self
    {
        return new self($taskId, $operation, null, $context);
    }

    /**
     * Create a TaskNotFoundException for bulk operations
     *
     * @param array $taskIds
     * @param string $operation
     * @return self
     */
    public static function forBulkOperation(array $taskIds, string $operation): self
    {
        $message = 'Multiple tasks not found during ' . $operation . ' operation';
        return new self(null, $operation, $message, ['task_ids' => $taskIds]);
    }
}