<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when task validation fails
 */
class TaskValidationException extends Exception
{
    /**
     * The validation errors
     *
     * @var array
     */
    protected array $errors;

    /**
     * The field that caused the validation error
     *
     * @var string|null
     */
    protected ?string $field;

    /**
     * Create a new TaskValidationException instance
     *
     * @param array $errors
     * @param string|null $field
     * @param string $message
     */
    public function __construct(array $errors = [], ?string $field = null, string $message = 'Task validation failed')
    {
        $this->errors = $errors;
        $this->field = $field;
        
        parent::__construct($message, 422);
    }

    /**
     * Get the validation errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the field that caused the validation error
     *
     * @return string|null
     */
    public function getField(): ?string
    {
        return $this->field;
    }

    /**
     * Get the error details for API response
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return [
            'success' => false,
            'error' => 'Validation failed',
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'field' => $this->field,
            'code' => 'VALIDATION_FAILED'
        ];
    }
}