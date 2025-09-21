<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use App\Http\Responses\ErrorResponseFormatter;

/**
 * Trait for consistent error response formatting
 * 
 * @deprecated Use ErrorResponseFormatter directly instead
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
        return ErrorResponseFormatter::format(
            $error,
            $message ?? $error,
            $statusCode,
            $code,
            $details
        );
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
        return ErrorResponseFormatter::validationError($errors, $message);
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
        return ErrorResponseFormatter::notFound($resource, $id);
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
        return ErrorResponseFormatter::databaseError($operation, $message);
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
        return ErrorResponseFormatter::unauthorized($message);
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
        return ErrorResponseFormatter::forbidden($message);
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
        return ErrorResponseFormatter::badRequest($message, $details);
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
        return ErrorResponseFormatter::serverError(
            $message ?? 'An unexpected error occurred',
            null,
            $details
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
        return ErrorResponseFormatter::methodNotAllowed($method, $allowedMethods);
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
        return ErrorResponseFormatter::format(
            'Conflict',
            $message,
            409,
            'CONFLICT',
            $details
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
        return ErrorResponseFormatter::rateLimitExceeded($message, $retryAfter);
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
        return ErrorResponseFormatter::format(
            'Unprocessable Entity',
            $message,
            422,
            'UNPROCESSABLE_ENTITY',
            ['errors' => $errors]
        );
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
        return ErrorResponseFormatter::fromException($exception, $context);
    }
}