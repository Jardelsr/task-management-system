<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Centralized Error Response Formatter for Lumen API
 * 
 * Provides consistent, standardized error responses across the entire application
 */
class ErrorResponseFormatter
{
    /**
     * Standard error response structure
     *
     * @param string $error The error type/category
     * @param string $message Human-readable error message
     * @param int $statusCode HTTP status code
     * @param string|null $errorCode Application-specific error code
     * @param array $details Additional error details
     * @param array $context Request context information
     * @return JsonResponse
     */
    public static function format(
        string $error,
        string $message,
        int $statusCode = 500,
        ?string $errorCode = null,
        array $details = [],
        array $context = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'timestamp' => Carbon::now()->toISOString(),
            'error' => $error,
            'message' => $message,
            'status_code' => $statusCode,
        ];

        // Add error code if provided
        if ($errorCode) {
            $response['error_code'] = $errorCode;
        }

        // Add details if provided
        if (!empty($details)) {
            $response['details'] = $details;
        }

        // Add context if provided
        if (!empty($context)) {
            $response['context'] = $context;
        }

        // Add request information if available
        if (request()) {
            $response['request_info'] = [
                'method' => request()->method(),
                'path' => request()->path(),
                'ip' => request()->ip(),
            ];
        }

        // Add debug information in debug mode
        if (config('app.debug')) {
            $response['debug'] = [
                'trace' => self::getSimplifiedTrace(),
                'environment' => config('app.env'),
            ];
        }

        return response()->json($response, $statusCode)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Error-Type' => $error,
                'X-Error-Code' => $errorCode ?: 'UNKNOWN',
                'X-Timestamp' => Carbon::now()->toISOString(),
            ]);
    }

    /**
     * Format validation error response
     *
     * @param array $errors Validation errors
     * @param string $message Main error message
     * @param array $context Additional context
     * @return JsonResponse
     */
    public static function validationError(
        array $errors,
        string $message = 'The given data was invalid',
        array $context = []
    ): JsonResponse {
        $details = [
            'errors' => self::formatValidationErrors($errors),
            'failed_fields' => array_keys($errors),
            'error_count' => count($errors),
        ];

        return self::format(
            'Validation Error',
            $message,
            422,
            'VALIDATION_FAILED',
            $details,
            $context
        );
    }

    /**
     * Format not found error response
     *
     * @param string $resource Resource type that was not found
     * @param mixed $id Resource ID (optional)
     * @param string|null $customMessage Custom error message
     * @param array $suggestions Helpful suggestions for the user
     * @return JsonResponse
     */
    public static function notFound(
        string $resource = 'Resource',
        $id = null,
        ?string $customMessage = null,
        array $suggestions = []
    ): JsonResponse {
        $message = $customMessage ?: ($id 
            ? "{$resource} with ID {$id} not found"
            : "{$resource} not found");

        $details = [
            'resource' => $resource,
        ];

        if ($id !== null) {
            $details['id'] = $id;
        }

        if (!empty($suggestions)) {
            $details['suggestions'] = $suggestions;
        }

        return self::format(
            'Not Found',
            $message,
            404,
            'RESOURCE_NOT_FOUND',
            $details
        );
    }

    /**
     * Format server error response
     *
     * @param string $message Error message
     * @param string|null $operation Operation that failed
     * @param array $details Additional details
     * @return JsonResponse
     */
    public static function serverError(
        string $message = 'An unexpected error occurred',
        ?string $operation = null,
        array $details = []
    ): JsonResponse {
        if ($operation) {
            $details['operation'] = $operation;
        }

        return self::format(
            'Internal Server Error',
            $message,
            500,
            'INTERNAL_ERROR',
            $details
        );
    }

    /**
     * Format database error response
     *
     * @param string $operation Database operation that failed
     * @param string|null $customMessage Custom error message
     * @param array $details Additional details
     * @return JsonResponse
     */
    public static function databaseError(
        string $operation,
        ?string $customMessage = null,
        array $details = []
    ): JsonResponse {
        $message = $customMessage ?: "Database operation failed: {$operation}";
        $details['operation'] = $operation;

        return self::format(
            'Database Error',
            $message,
            500,
            'DATABASE_ERROR',
            $details
        );
    }

    /**
     * Format database connection error response
     *
     * @param string $connectionType Type of database connection (mysql, mongodb)
     * @param string $message Error message
     * @param int $attempts Number of failed attempts
     * @param bool $isTemporary Whether the error is temporary
     * @param int $retryDelay Recommended retry delay in seconds
     * @return JsonResponse
     */
    public static function databaseConnectionError(
        string $connectionType,
        string $message,
        int $attempts = 1,
        bool $isTemporary = false,
        int $retryDelay = 30
    ): JsonResponse {
        $statusCode = 503; // Service Unavailable
        $details = [
            'connection_type' => $connectionType,
            'attempts' => $attempts,
            'is_temporary' => $isTemporary,
            'retry_delay_seconds' => $retryDelay,
            'suggestion' => self::getConnectionSuggestion($connectionType),
            'troubleshooting' => self::getConnectionTroubleshooting($connectionType)
        ];

        $response = self::format(
            'Database Connection Error',
            $message,
            $statusCode,
            'DATABASE_CONNECTION_ERROR',
            $details
        );

        // Add retry-after header for temporary errors
        if ($isTemporary) {
            $response->header('Retry-After', $retryDelay);
        }

        return $response;
    }

    /**
     * Get connection suggestion based on connection type
     *
     * @param string $connectionType
     * @return string
     */
    private static function getConnectionSuggestion(string $connectionType): string
    {
        switch ($connectionType) {
            case 'mysql':
                return 'Check if MySQL server is running and accessible. Verify database credentials and network connectivity.';
            case 'mongodb':
                return 'Check if MongoDB server is running and accessible. Verify MongoDB configuration and network connectivity.';
            default:
                return 'Check if the database server is running and accessible. Verify connection configuration.';
        }
    }

    /**
     * Get troubleshooting steps for connection issues
     *
     * @param string $connectionType
     * @return array
     */
    private static function getConnectionTroubleshooting(string $connectionType): array
    {
        $common = [
            'Verify database server is running',
            'Check network connectivity',
            'Confirm firewall settings allow database connections',
            'Validate connection configuration and credentials'
        ];

        switch ($connectionType) {
            case 'mysql':
                return array_merge($common, [
                    'Check MySQL service status',
                    'Verify MySQL port 3306 is accessible',
                    'Review MySQL error logs',
                    'Check MySQL connection limits'
                ]);
            case 'mongodb':
                return array_merge($common, [
                    'Check MongoDB service status',
                    'Verify MongoDB port 27017 is accessible',
                    'Review MongoDB logs',
                    'Check MongoDB replica set configuration (if applicable)'
                ]);
            default:
                return $common;
        }
    }

    /**
     * Format unauthorized error response
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    public static function unauthorized(
        string $message = 'Authentication required'
    ): JsonResponse {
        return self::format(
            'Unauthorized',
            $message,
            401,
            'UNAUTHORIZED'
        );
    }

    /**
     * Format forbidden error response
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    public static function forbidden(
        string $message = 'Access denied'
    ): JsonResponse {
        return self::format(
            'Forbidden',
            $message,
            403,
            'FORBIDDEN'
        );
    }

    /**
     * Format bad request error response
     *
     * @param string $message Error message
     * @param array $details Additional details
     * @return JsonResponse
     */
    public static function badRequest(
        string $message = 'Bad request',
        array $details = []
    ): JsonResponse {
        return self::format(
            'Bad Request',
            $message,
            400,
            'BAD_REQUEST',
            $details
        );
    }

    /**
     * Format method not allowed error response
     *
     * @param string $method HTTP method used
     * @param array $allowedMethods Allowed HTTP methods
     * @return JsonResponse
     */
    public static function methodNotAllowed(
        string $method,
        array $allowedMethods = []
    ): JsonResponse {
        $details = [
            'method' => $method,
            'allowed_methods' => $allowedMethods,
        ];

        return self::format(
            'Method Not Allowed',
            "The {$method} method is not allowed for this endpoint",
            405,
            'METHOD_NOT_ALLOWED',
            $details
        );
    }

    /**
     * Format rate limit error response
     *
     * @param string $message Error message
     * @param int $retryAfter Retry after seconds
     * @return JsonResponse
     */
    public static function rateLimitExceeded(
        string $message = 'Rate limit exceeded',
        int $retryAfter = 60
    ): JsonResponse {
        $details = [
            'retry_after' => $retryAfter,
        ];

        $response = self::format(
            'Too Many Requests',
            $message,
            429,
            'RATE_LIMIT_EXCEEDED',
            $details
        );

        return $response->header('Retry-After', $retryAfter);
    }

    /**
     * Format validation errors into a structured format
     *
     * @param array $errors Raw validation errors
     * @return array Formatted errors
     */
    private static function formatValidationErrors(array $errors): array
    {
        $formatted = [];

        foreach ($errors as $field => $messages) {
            $messageArray = is_array($messages) ? $messages : [$messages];
            $formatted[$field] = [
                'messages' => $messageArray,
                'first_message' => reset($messageArray),
            ];
        }

        return $formatted;
    }

    /**
     * Get simplified trace for debugging
     *
     * @return array
     */
    private static function getSimplifiedTrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $simplified = [];

        foreach ($trace as $frame) {
            $simplified[] = [
                'file' => basename($frame['file'] ?? 'unknown'),
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
            ];
        }

        return $simplified;
    }

    /**
     * Format custom exception error response
     *
     * @param \Throwable $exception The exception
     * @param array $context Additional context
     * @return JsonResponse
     */
    public static function fromException(
        \Throwable $exception,
        array $context = []
    ): JsonResponse {
        $statusCode = method_exists($exception, 'getStatusCode') 
            ? $exception->getStatusCode() 
            : ($exception->getCode() ?: 500);

        $errorCode = method_exists($exception, 'getErrorCode')
            ? $exception->getErrorCode()
            : strtoupper(str_replace('\\', '_', class_basename($exception)));

        $details = [];
        if (method_exists($exception, 'getErrorDetails')) {
            $details = $exception->getErrorDetails();
        }

        return self::format(
            class_basename($exception),
            $exception->getMessage(),
            $statusCode,
            $errorCode,
            $details,
            $context
        );
    }
}