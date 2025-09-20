<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait for consistent error response formatting
 */
trait ErrorResponseTrait
{
    use ApiHeadersTrait;
    /**
     * Create a standardized error response
     *
     * @param string $error
     * @param string|null $message
     * @param int $statusCode
     * @param array $details
     * @param string|null $code
     * @return JsonResponse
     */
    protected function errorResponse(
        string $error,
        ?string $message = null,
        int $statusCode = 500,
        array $details = [],
        ?string $code = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => $error,
            'message' => $message ?? $error,
            'timestamp' => \Carbon\Carbon::now()->toISOString()
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        if ($code) {
            $response['code'] = $code;
        }

        if (config('app.debug')) {
            $response['debug'] = [
                'file' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['file'] ?? null,
                'line' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['line'] ?? null,
            ];
        }

        $jsonResponse = response()->json($response, $statusCode);

        // Add consistent API headers for error responses too
        return $this->addApiHeaders($jsonResponse);
    }

    /**
     * Create a validation error response
     *
     * @param array $errors
     * @param string $message
     * @return JsonResponse
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return $this->errorResponse(
            'Validation failed',
            $message,
            422,
            ['errors' => $errors],
            'VALIDATION_FAILED'
        );
    }

    /**
     * Create a not found error response
     *
     * @param string $resource
     * @param int|string|null $id
     * @return JsonResponse
     */
    protected function notFoundResponse(
        string $resource = 'Resource',
        $id = null
    ): JsonResponse {
        $message = $id 
            ? "{$resource} with ID {$id} not found"
            : "{$resource} not found";

        return $this->errorResponse(
            'Not found',
            $message,
            404,
            ['resource' => $resource, 'id' => $id],
            'RESOURCE_NOT_FOUND'
        );
    }

    /**
     * Create a database error response
     *
     * @param string $operation
     * @param string|null $message
     * @return JsonResponse
     */
    protected function databaseErrorResponse(
        string $operation,
        ?string $message = null
    ): JsonResponse {
        return $this->errorResponse(
            'Database operation failed',
            $message ?? "Failed to {$operation}",
            500,
            ['operation' => $operation],
            'DATABASE_ERROR'
        );
    }

    /**
     * Create an unauthorized error response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(
        string $message = 'Unauthorized access'
    ): JsonResponse {
        return $this->errorResponse(
            'Unauthorized',
            $message,
            401,
            [],
            'UNAUTHORIZED'
        );
    }

    /**
     * Create a forbidden error response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function forbiddenResponse(
        string $message = 'Access forbidden'
    ): JsonResponse {
        return $this->errorResponse(
            'Forbidden',
            $message,
            403,
            [],
            'FORBIDDEN'
        );
    }

    /**
     * Create a bad request error response
     *
     * @param string $message
     * @param array $details
     * @return JsonResponse
     */
    protected function badRequestResponse(
        string $message = 'Bad request',
        array $details = []
    ): JsonResponse {
        return $this->errorResponse(
            'Bad request',
            $message,
            400,
            $details,
            'BAD_REQUEST'
        );
    }

    /**
     * Create a server error response
     *
     * @param string|null $message
     * @param array $details
     * @return JsonResponse
     */
    protected function serverErrorResponse(
        ?string $message = null,
        array $details = []
    ): JsonResponse {
        return $this->errorResponse(
            'Internal server error',
            $message ?? 'An unexpected error occurred',
            500,
            $details,
            'INTERNAL_ERROR'
        );
    }

    /**
     * Create a method not allowed error response
     *
     * @param string $method
     * @param array $allowedMethods
     * @return JsonResponse
     */
    protected function methodNotAllowedResponse(
        string $method,
        array $allowedMethods = []
    ): JsonResponse {
        return $this->errorResponse(
            'Method not allowed',
            "The {$method} method is not allowed for this endpoint",
            405,
            ['method' => $method, 'allowed_methods' => $allowedMethods],
            'METHOD_NOT_ALLOWED'
        );
    }

    /**
     * Create a conflict error response
     *
     * @param string $message
     * @param array $details
     * @return JsonResponse
     */
    protected function conflictResponse(
        string $message = 'Resource conflict',
        array $details = []
    ): JsonResponse {
        return $this->errorResponse(
            'Conflict',
            $message,
            409,
            $details,
            'CONFLICT'
        );
    }

    /**
     * Create a too many requests error response
     *
     * @param string|null $message
     * @param int $retryAfter
     * @return JsonResponse
     */
    protected function tooManyRequestsResponse(
        ?string $message = null,
        int $retryAfter = 60
    ): JsonResponse {
        $response = $this->errorResponse(
            'Too many requests',
            $message ?? 'Rate limit exceeded',
            429,
            ['retry_after' => $retryAfter],
            'RATE_LIMIT_EXCEEDED'
        );

        return $response->header('Retry-After', $retryAfter);
    }

    /**
     * Create an unprocessable entity error response
     *
     * @param string $message
     * @param array $errors
     * @return JsonResponse
     */
    protected function unprocessableEntityResponse(
        string $message = 'Unprocessable entity',
        array $errors = []
    ): JsonResponse {
        return $this->errorResponse(
            'Unprocessable entity',
            $message,
            422,
            ['errors' => $errors],
            'UNPROCESSABLE_ENTITY'
        );
    }

    /**
     * Create a service unavailable error response
     *
     * @param string|null $message
     * @param int $retryAfter
     * @return JsonResponse
     */
    protected function serviceUnavailableResponse(
        ?string $message = null,
        int $retryAfter = 300
    ): JsonResponse {
        $response = $this->errorResponse(
            'Service unavailable',
            $message ?? 'Service is temporarily unavailable',
            503,
            ['retry_after' => $retryAfter],
            'SERVICE_UNAVAILABLE'
        );

        return $response->header('Retry-After', $retryAfter);
    }

    /**
     * Create a custom exception error response
     *
     * @param \Throwable $exception
     * @param array $context
     * @return JsonResponse
     */
    protected function exceptionResponse(
        \Throwable $exception,
        array $context = []
    ): JsonResponse {
        $statusCode = method_exists($exception, 'getStatusCode') 
            ? $exception->getStatusCode() 
            : ($exception->getCode() ?: 500);

        $details = $context;
        
        if (method_exists($exception, 'getErrorDetails')) {
            $details = array_merge($details, $exception->getErrorDetails());
        }

        return $this->errorResponse(
            class_basename($exception),
            $exception->getMessage(),
            $statusCode,
            $details,
            'EXCEPTION_ERROR'
        );
    }
}