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
     * Get the error details for API response with enhanced formatting
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return [
            'success' => false,
            'timestamp' => \Carbon\Carbon::now()->toISOString(),
            'error' => 'Task Validation Error',
            'message' => $this->getMessage(),
            'status_code' => 422,
            'error_code' => 'TASK_VALIDATION_FAILED',
            'validation' => [
                'errors' => $this->formatValidationErrors(),
                'failed_fields' => array_keys($this->errors),
                'error_count' => count($this->errors),
                'field_context' => $this->field,
            ]
        ];
    }

    /**
     * Format validation errors with detailed structure
     *
     * @return array
     */
    private function formatValidationErrors(): array
    {
        $formatted = [];

        foreach ($this->errors as $field => $messages) {
            $messageArray = is_array($messages) ? $messages : [$messages];
            
            $formatted[$field] = [
                'messages' => $messageArray,
                'first_message' => reset($messageArray),
                'severity' => $this->getErrorSeverity($field, $messageArray),
                'suggestions' => $this->getFieldSuggestions($field, $messageArray),
            ];
        }

        return $formatted;
    }

    /**
     * Get error severity based on field and messages
     *
     * @param string $field
     * @param array $messages
     * @return string
     */
    private function getErrorSeverity(string $field, array $messages): string
    {
        // Critical fields that must be valid
        $criticalFields = ['title', 'status'];
        
        if (in_array($field, $criticalFields)) {
            return 'high';
        }
        
        // Check for required field errors
        foreach ($messages as $message) {
            if (strpos($message, 'required') !== false) {
                return 'high';
            }
        }
        
        return 'medium';
    }

    /**
     * Get suggestions for fixing validation errors
     *
     * @param string $field
     * @param array $messages
     * @return array
     */
    private function getFieldSuggestions(string $field, array $messages): array
    {
        $suggestions = [];
        
        foreach ($messages as $message) {
            if (strpos($message, 'required') !== false) {
                $suggestions[] = "The {$field} field must be provided.";
            } elseif (strpos($message, 'string') !== false) {
                $suggestions[] = "The {$field} field must be a text value.";
            } elseif (strpos($message, 'integer') !== false) {
                $suggestions[] = "The {$field} field must be a whole number.";
            } elseif (strpos($message, 'date') !== false) {
                $suggestions[] = "The {$field} field must be a valid date format (YYYY-MM-DD HH:mm:ss).";
            } elseif (strpos($message, 'greater than') !== false && strpos($message, 'characters') !== false) {
                $suggestions[] = "The {$field} field is too long. Please reduce the length.";
            } elseif (strpos($message, 'may not be greater than') !== false) {
                $suggestions[] = "The {$field} field is too long. Please reduce the length.";
            } elseif (strpos($message, 'max') !== false) {
                $suggestions[] = "The {$field} field is too long. Please reduce the length.";
            } elseif (strpos($message, 'min') !== false) {
                $suggestions[] = "The {$field} field is too short. Please provide more content.";
            } elseif (strpos($message, 'selected') !== false) {
                if ($field === 'status') {
                    $suggestions[] = "Valid status values are: " . implode(', ', \App\Models\Task::getAvailableStatuses());
                } elseif ($field === 'priority') {
                    $suggestions[] = "Valid priority values are: low, medium, high";
                } else {
                    $suggestions[] = "The selected {$field} value is invalid.";
                }
            } else {
                // Default suggestion for any validation error
                $suggestions[] = "Please check the {$field} field and ensure it meets the requirements.";
            }
        }
        
        return array_unique($suggestions);
    }
}