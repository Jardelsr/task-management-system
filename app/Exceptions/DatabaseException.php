<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when there's an error with database operations
 */
class DatabaseException extends Exception
{
    /**
     * The operation that failed
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
     * Create a new DatabaseException instance
     *
     * @param string $message
     * @param string|null $operation
     * @param array $context
     * @param int $code
     */
    public function __construct(
        string $message = 'Database operation failed',
        ?string $operation = null,
        array $context = [],
        int $code = 500
    ) {
        $this->operation = $operation;
        $this->context = $context;
        
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
        return [
            'error' => 'Database operation failed',
            'message' => $this->getMessage(),
            'operation' => $this->operation,
            'code' => 'DATABASE_ERROR'
        ];
    }
}