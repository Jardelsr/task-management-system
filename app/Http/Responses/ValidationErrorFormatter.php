<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Enhanced Validation Error Response Formatter for Lumen
 * 
 * Provides consistent, structured validation error responses
 * with detailed field-level error information and metadata
 */
class ValidationErrorFormatter
{
    /**
     * Format validation errors into a consistent response structure
     *
     * @param array $errors Array of validation errors
     * @param string $message Main error message
     * @param array $context Additional context information
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    public static function format(
        array $errors, 
        string $message = 'The given data was invalid',
        array $context = [],
        int $statusCode = 422
    ): JsonResponse {
        $response = [
            'success' => false,
            'timestamp' => Carbon::now()->toISOString(),
            'error' => 'Validation Error',
            'message' => $message,
            'status_code' => $statusCode,
            'error_code' => 'VALIDATION_FAILED',
            'validation' => [
                'errors' => self::formatErrors($errors),
                'failed_fields' => array_keys($errors),
                'error_count' => count($errors),
            ]
        ];

        // Add context if provided
        if (!empty($context)) {
            $response['context'] = $context;
        }

        // Add debug information in debug mode
        if (config('app.debug')) {
            $response['debug'] = [
                'raw_errors' => $errors,
                'trace_summary' => self::getTraceContext(),
            ];
        }

        // Add request information if available
        if (request()) {
            $response['request_info'] = [
                'method' => request()->method(),
                'url' => request()->fullUrl(),
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip(),
            ];
        }

        return response()->json($response, $statusCode)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Error-Type' => 'validation',
                'X-Error-Count' => count($errors),
                'X-Timestamp' => Carbon::now()->toISOString(),
            ]);
    }

    /**
     * Format validation errors from a ValidationException
     *
     * @param ValidationException $exception
     * @param array $context Additional context
     * @return JsonResponse
     */
    public static function fromValidationException(
        ValidationException $exception, 
        array $context = []
    ): JsonResponse {
        return self::format(
            $exception->errors(),
            $exception->getMessage() ?: 'The given data was invalid',
            $context,
            422
        );
    }

    /**
     * Format errors into a structured format with field details
     *
     * @param array $errors Raw validation errors
     * @return array Formatted errors
     */
    private static function formatErrors(array $errors): array
    {
        $formatted = [];

        foreach ($errors as $field => $messages) {
            $formatted[$field] = [
                'messages' => is_array($messages) ? $messages : [$messages],
                'first_message' => is_array($messages) ? reset($messages) : $messages,
                'rule_failures' => self::extractRuleFailures($messages),
            ];
        }

        return $formatted;
    }

    /**
     * Extract failed validation rules from error messages
     *
     * @param array|string $messages Error messages
     * @return array Array of failed rules
     */
    private static function extractRuleFailures($messages): array
    {
        $rules = [];
        $messageArray = is_array($messages) ? $messages : [$messages];

        foreach ($messageArray as $message) {
            // Common validation rule patterns
            if (strpos($message, 'required') !== false) {
                $rules[] = 'required';
            }
            if (strpos($message, 'must be') !== false && strpos($message, 'characters') !== false) {
                $rules[] = preg_match('/min:\d+|max:\d+/', $message, $matches) ? $matches[0] : 'length';
            }
            if (strpos($message, 'valid') !== false && strpos($message, 'format') !== false) {
                $rules[] = 'format';
            }
            if (strpos($message, 'integer') !== false) {
                $rules[] = 'integer';
            }
            if (strpos($message, 'string') !== false) {
                $rules[] = 'string';
            }
            if (strpos($message, 'date') !== false) {
                $rules[] = 'date';
            }
            if (strpos($message, 'selected') !== false && strpos($message, 'invalid') !== false) {
                $rules[] = 'in';
            }
        }

        return array_unique($rules);
    }

    /**
     * Get simplified trace context for debugging
     *
     * @return array
     */
    private static function getTraceContext(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $context = [];

        foreach ($trace as $index => $frame) {
            if ($index > 2) break; // Only get first few frames
            
            $context[] = [
                'file' => basename($frame['file'] ?? 'unknown'),
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
            ];
        }

        return $context;
    }

    /**
     * Create a validation error response for missing required fields
     *
     * @param array $missingFields Array of missing field names
     * @param string $message Custom message
     * @return JsonResponse
     */
    public static function missingFields(
        array $missingFields, 
        string $message = 'Required fields are missing'
    ): JsonResponse {
        $errors = [];
        foreach ($missingFields as $field) {
            $errors[$field] = ["The {$field} field is required."];
        }

        return self::format($errors, $message, [
            'missing_fields' => $missingFields,
            'suggestion' => 'Please provide all required fields in your request.'
        ]);
    }

    /**
     * Create a validation error response for invalid field formats
     *
     * @param array $invalidFields Array of field names with their format requirements
     * @param string $message Custom message
     * @return JsonResponse
     */
    public static function invalidFormats(
        array $invalidFields, 
        string $message = 'Some fields have invalid formats'
    ): JsonResponse {
        $errors = [];
        foreach ($invalidFields as $field => $requirement) {
            $errors[$field] = ["The {$field} field must be a valid {$requirement}."];
        }

        return self::format($errors, $message, [
            'invalid_formats' => $invalidFields,
            'suggestion' => 'Please check the format requirements for each field.'
        ]);
    }
}