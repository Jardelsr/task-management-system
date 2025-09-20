<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when logging operations fail
 */
class LoggingException extends Exception
{
    /**
     * The log operation that failed
     *
     * @var string|null
     */
    protected ?string $logOperation;

    /**
     * Additional context about the logging failure
     *
     * @var array
     */
    protected array $context;

    /**
     * Create a new LoggingException instance
     *
     * @param string $message
     * @param string|null $logOperation
     * @param array $context
     */
    public function __construct(
        string $message = 'Logging operation failed',
        ?string $logOperation = null,
        array $context = []
    ) {
        $this->logOperation = $logOperation;
        $this->context = $context;
        
        parent::__construct($message, 500);
    }

    /**
     * Get the log operation that failed
     *
     * @return string|null
     */
    public function getLogOperation(): ?string
    {
        return $this->logOperation;
    }

    /**
     * Get additional context about the logging failure
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
            'error' => 'Logging operation failed',
            'message' => $this->getMessage(),
            'log_operation' => $this->logOperation,
            'code' => 'LOGGING_FAILED'
        ];
    }
}