<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait for consistent error response formatting
 */
trait ErrorResponseTrait
{
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
            'timestamp' => now()->toISOString()
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

        return response()->json($response, $statusCode);
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
     * Create a success response
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function successResponse(
        $data = null,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'timestamp' => now()->toISOString()
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }
}