<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Http\JsonResponse;
use App\Traits\ErrorResponseTrait;
use App\Http\Responses\ValidationErrorFormatter;
use Throwable;

class Handler extends ExceptionHandler
{
    use ErrorResponseTrait;

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        TaskNotFoundException::class,
        TaskValidationException::class,
        RateLimitException::class, // Don't report rate limit exceptions
        TaskValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        // Log database exceptions with additional context
        if ($exception instanceof DatabaseException) {
            \Log::error('Database operation failed', [
                'operation' => $exception->getOperation(),
                'context' => $exception->getContext(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]);
        }

        // Log task operation exceptions
        if ($exception instanceof TaskOperationException) {
            \Log::warning('Task operation failed', [
                'operation' => $exception->getOperation(),
                'task_id' => $exception->getTaskId(),
                'message' => $exception->getMessage()
            ]);
        }

        // Log logging exceptions (with caution to avoid recursion)
        if ($exception instanceof LoggingException) {
            error_log("Logging system failure: " . $exception->getMessage());
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // Handle rate limiting exceptions first (high priority)
        if ($exception instanceof RateLimitException) {
            $response = response()->json($exception->getErrorDetails(), $exception->getCode());
            
            // Add rate limiting headers
            foreach ($exception->getHttpHeaders() as $header => $value) {
                $response->header($header, $value);
            }
            
            return $response;
        }

        // Handle custom task exceptions with enhanced logging
        if ($exception instanceof TaskNotFoundException) {
            return response()->json($exception->getErrorDetails(), $exception->getCode());
        }

        if ($exception instanceof TaskValidationException) {
            return response()->json($exception->getErrorDetails(), $exception->getCode());
        }

        if ($exception instanceof DatabaseException) {
            return response()->json($exception->getErrorDetails(), $exception->getCode());
        }

        if ($exception instanceof TaskOperationException) {
            return response()->json($exception->getErrorDetails(), $exception->getCode());
        }

        if ($exception instanceof TaskRestoreException) {
            return response()->json($exception->getErrorDetails(), $exception->getCode());
        }

        if ($exception instanceof LoggingException) {
            // For validation-type logging exceptions, return proper error details
            if ($exception->getLogOperation() === 'validation') {
                return response()->json([
                    'success' => false,
                    'timestamp' => \Carbon\Carbon::now()->toISOString(),
                    'error' => 'Validation Error',
                    'message' => $exception->getMessage(),
                    'context' => $exception->getContext(),
                    'error_code' => 'VALIDATION_ERROR'
                ], 422);
            }
            
            // For find_by_id operations, return 404
            if ($exception->getLogOperation() === 'find_by_id') {
                return response()->json([
                    'success' => false,
                    'timestamp' => \Carbon\Carbon::now()->toISOString(),
                    'error' => 'Not Found',
                    'message' => $exception->getMessage(),
                    'context' => $exception->getContext(),
                    'error_code' => 'RESOURCE_NOT_FOUND'
                ], 404);
            }
            
            // For other logging exceptions, return a generic error to avoid exposing internal details
            return $this->serverErrorResponse(
                'An error occurred while processing your request'
            );
        }

        // Handle Laravel validation exceptions with enhanced formatting
        if ($exception instanceof ValidationException) {
            return ValidationErrorFormatter::fromValidationException($exception, [
                'request_method' => $request->method(),
                'request_path' => $request->path(),
            ]);
        }

        // Handle model not found exceptions - convert to our custom format
        if ($exception instanceof ModelNotFoundException) {
            $model = class_basename($exception->getModel());
            
            // If it's a Task model, use our custom TaskNotFoundException format
            if ($model === 'Task') {
                $taskNotFoundException = new TaskNotFoundException(null, 'model_query', 'Task not found in database');
                return response()->json($taskNotFoundException->getErrorDetails(), 404);
            }
            
            return $this->notFoundResponse($model);
        }

        // Handle HTTP exceptions
        if ($exception instanceof NotFoundHttpException) {
            return $this->errorResponse(
                'Route not found',
                'The requested endpoint does not exist. Please check the URL and method.',
                404,
                ['available_endpoints' => $this->getAvailableEndpoints()],
                'ROUTE_NOT_FOUND'
            );
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->errorResponse(
                'Method not allowed',
                'The requested HTTP method is not allowed for this endpoint',
                405,
                ['allowed_methods' => $exception->getHeaders()['Allow'] ?? []],
                'METHOD_NOT_ALLOWED'
            );
        }

        if ($exception instanceof HttpException) {
            return $this->errorResponse(
                'HTTP Error',
                $exception->getMessage() ?: 'An HTTP error occurred',
                $exception->getStatusCode(),
                [],
                'HTTP_ERROR'
            );
        }

        if ($exception instanceof AuthorizationException) {
            return $this->forbiddenResponse($exception->getMessage());
        }

        // For all other exceptions in production, return a generic error
        if (!config('app.debug')) {
            // Log the full error for debugging
            $this->report($exception);
            
            return $this->serverErrorResponse(
                'An unexpected error occurred. Please try again later.'
            );
        }

        // In debug mode, let Laravel handle it to show detailed error information
        return parent::render($request, $exception);
    }

    /**
     * Get available API endpoints for 404 responses
     *
     * @return array
     */
    private function getAvailableEndpoints(): array
    {
        return [
            'GET /tasks' => 'List all tasks',
            'GET /tasks/{id}' => 'Get specific task',
            'POST /tasks' => 'Create new task',
            'PUT /tasks/{id}' => 'Update task',
            'DELETE /tasks/{id}' => 'Delete task',
            'GET /tasks/stats' => 'Get task statistics',
            'GET /logs' => 'Get system logs',
            'GET /logs/{id}' => 'Get specific log entry'
        ];
    }
}