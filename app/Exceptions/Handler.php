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
use App\Http\Responses\ErrorResponseFormatter;
use App\Http\Responses\ValidationErrorFormatter;
use App\Services\ErrorLoggingService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Throwable;
use Illuminate\Database\QueryException;
use PDOException;

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
        try {
            // Get current request if available
            $request = app('request');
            
            // Use enhanced error logging service
            ErrorLoggingService::logError($exception, $request, [
                'reported_at' => Carbon::now()->toISOString(),
                'should_report' => $this->shouldReport($exception),
                'context' => 'exception_handler'
            ]);

            // Log database exceptions with additional context
            if ($exception instanceof DatabaseException) {
                ErrorLoggingService::logDatabaseError($exception, 
                    method_exists($exception, 'getOperation') ? $exception->getOperation() : null,
                    [
                        'context' => method_exists($exception, 'getContext') ? $exception->getContext() : [],
                        'operation' => method_exists($exception, 'getOperation') ? $exception->getOperation() : 'unknown'
                    ]
                );
            }

            // Log task operation exceptions
            if ($exception instanceof TaskOperationException) {
                ErrorLoggingService::logTaskOperation(
                    method_exists($exception, 'getOperation') ? $exception->getOperation() : 'unknown_operation',
                    method_exists($exception, 'getTaskId') ? $exception->getTaskId() : null,
                    ['error' => $exception->getMessage()],
                    'error'
                );
            }

            // Log logging exceptions (with caution to avoid recursion)
            if ($exception instanceof LoggingException) {
                error_log("Logging system failure: " . $exception->getMessage() . " at " . $exception->getFile() . ":" . $exception->getLine());
            }

        } catch (Throwable $loggingException) {
            // Fallback logging to prevent recursion
            error_log("Failed to log exception: " . $loggingException->getMessage());
            error_log("Original exception: " . $exception->getMessage());
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
        try {
            // Log the rendering of exception responses
            ErrorLoggingService::logApiOperation($request, null, 'warning', [
                'context' => 'exception_response',
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage()
            ]);

            // Handle rate limiting exceptions first (high priority)
            if ($exception instanceof RateLimitException) {
                $response = ErrorResponseFormatter::rateLimitExceeded(
                    $exception->getMessage(),
                    method_exists($exception, 'getRetryAfter') ? $exception->getRetryAfter() : 60
                );
                
                // Add any additional rate limiting headers
                if (method_exists($exception, 'getHttpHeaders')) {
                    foreach ($exception->getHttpHeaders() as $header => $value) {
                        $response->header($header, $value);
                    }
                }
                
                return $response;
            }

            // Handle custom task exceptions
            if ($exception instanceof TaskNotFoundException) {
                $suggestions = [];
                if (method_exists($exception, 'getSuggestions')) {
                    $suggestions = $exception->getSuggestions();
                }
                
                return ErrorResponseFormatter::notFound(
                    'Task',
                    method_exists($exception, 'getTaskId') ? $exception->getTaskId() : null,
                    $exception->getMessage(),
                    $suggestions
                );
            }

            if ($exception instanceof TaskValidationException) {
                $errors = method_exists($exception, 'getErrors') ? $exception->getErrors() : [];
                ErrorLoggingService::logValidationError($errors, $request, [
                    'exception_type' => 'TaskValidationException',
                    'field_context' => method_exists($exception, 'getField') ? $exception->getField() : null
                ]);

                return ErrorResponseFormatter::validationError(
                    $errors,
                    $exception->getMessage(),
                    [
                        'field_context' => method_exists($exception, 'getField') ? $exception->getField() : null
                    ]
                );
            }

            // Handle database connection exceptions specifically
            if ($exception instanceof DatabaseConnectionException) {
                ErrorLoggingService::logDatabaseError($exception, 'connection_failure', [
                    'connection_type' => $exception->getConnectionType(),
                    'attempts' => $exception->getAttempts(),
                    'is_temporary' => $exception->isTemporary(),
                    'retry_delay' => $exception->getRetryDelay()
                ]);

                return ErrorResponseFormatter::databaseConnectionError(
                    $exception->getConnectionType(),
                    $exception->getMessage(),
                    $exception->getAttempts(),
                    $exception->isTemporary(),
                    $exception->getRetryDelay()
                );
            }

            if ($exception instanceof DatabaseException) {
                ErrorLoggingService::logDatabaseError($exception, 
                    method_exists($exception, 'getOperation') ? $exception->getOperation() : 'unknown'
                );

                return ErrorResponseFormatter::databaseError(
                    method_exists($exception, 'getOperation') ? $exception->getOperation() : 'unknown',
                    $exception->getMessage()
                );
            }

            // Handle PDO exceptions (low-level database connection errors)
            if ($exception instanceof PDOException || $exception instanceof QueryException) {
                ErrorLoggingService::logDatabaseError($exception, 'database_query', [
                    'error_code' => $exception->getCode(),
                    'is_connection_error' => $this->isConnectionError($exception)
                ]);

                // Check if it's a connection-related error
                if ($this->isConnectionError($exception)) {
                    return ErrorResponseFormatter::databaseConnectionError(
                        'mysql',
                        'Database connection failed: ' . $exception->getMessage(),
                        1,
                        true,
                        30
                    );
                }

                return ErrorResponseFormatter::databaseError(
                    'query_execution',
                    'Database query failed: ' . $exception->getMessage()
                );
            }

            if ($exception instanceof TaskOperationException) {
                return ErrorResponseFormatter::fromException($exception);
            }

            if ($exception instanceof TaskRestoreException) {
                return ErrorResponseFormatter::fromException($exception);
            }

            if ($exception instanceof LoggingException) {
                // Handle different types of logging exceptions
                $logOperation = method_exists($exception, 'getLogOperation') 
                    ? $exception->getLogOperation() 
                    : 'unknown';
                    
                switch ($logOperation) {
                    case 'validation':
                        return ErrorResponseFormatter::validationError(
                            ['general' => [$exception->getMessage()]],
                            'Validation Error',
                            method_exists($exception, 'getContext') ? $exception->getContext() : []
                        );
                        
                    case 'find_by_id':
                        return ErrorResponseFormatter::notFound(
                            'Resource',
                            null,
                            $exception->getMessage()
                        );
                        
                    default:
                        return ErrorResponseFormatter::serverError(
                            'An error occurred while processing your request',
                            $logOperation
                        );
                }
            }

            // Handle Laravel validation exceptions
            if ($exception instanceof ValidationException) {
                ErrorLoggingService::logValidationError($exception->errors(), $request, [
                    'exception_type' => 'ValidationException',
                    'validation_message' => $exception->getMessage()
                ]);

                return ErrorResponseFormatter::validationError(
                    $exception->errors(),
                    $exception->getMessage() ?: 'The given data was invalid',
                    [
                        'request_method' => $request->method(),
                        'request_path' => $request->path(),
                    ]
                );
            }

            // Handle model not found exceptions
            if ($exception instanceof ModelNotFoundException) {
                $model = class_basename($exception->getModel());
                
                ErrorLoggingService::logError($exception, $request, [
                    'model_type' => $model,
                    'exception_type' => 'ModelNotFoundException'
                ]);

                return ErrorResponseFormatter::notFound(
                    $model,
                    null,
                    "{$model} not found"
                );
            }

            // Handle HTTP exceptions
            if ($exception instanceof NotFoundHttpException) {
                return ErrorResponseFormatter::notFound(
                    'Route',
                    null,
                    'The requested endpoint does not exist',
                    $this->getAvailableEndpoints()
                );
            }

            if ($exception instanceof MethodNotAllowedHttpException) {
                $allowedMethods = isset($exception->getHeaders()['Allow']) 
                    ? explode(', ', $exception->getHeaders()['Allow']) 
                    : [];
                    
                return ErrorResponseFormatter::methodNotAllowed(
                    $request->method(),
                    $allowedMethods
                );
            }

            if ($exception instanceof HttpException) {
                return ErrorResponseFormatter::format(
                    'HTTP Error',
                    $exception->getMessage() ?: 'An HTTP error occurred',
                    $exception->getStatusCode(),
                    'HTTP_ERROR'
                );
            }

            if ($exception instanceof AuthorizationException) {
                return ErrorResponseFormatter::forbidden($exception->getMessage());
            }

            // For all other exceptions in production, return a generic error
            if (!config('app.debug')) {
                // Ensure the full error is logged
                ErrorLoggingService::logError($exception, $request, [
                    'context' => 'production_error_fallback',
                    'user_ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                
                return ErrorResponseFormatter::serverError(
                    'An unexpected error occurred. Please try again later.',
                    'exception_handling'
                );
            }

            // In debug mode, let Laravel handle it to show detailed error information
            return parent::render($request, $exception);

        } catch (Throwable $renderingException) {
            // Fallback error handling if something goes wrong during rendering
            error_log("Failed to render exception response: " . $renderingException->getMessage());
            error_log("Original exception: " . $exception->getMessage());
            
            return response()->json([
                'error' => 'An unexpected error occurred',
                'message' => 'Please try again later',
                'status_code' => 500
            ], 500);
        }
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

    /**
     * Check if exception is connection-related
     *
     * @param Throwable $e
     * @return bool
     */
    private function isConnectionError(Throwable $e): bool
    {
        $connectionErrorCodes = [
            2002, // Connection refused
            2003, // Can't connect to MySQL server
            2006, // MySQL server has gone away
            2013, // Lost connection to MySQL server during query
            1040, // Too many connections
            1129, // Host is blocked because of too many connection errors
            1203, // User already has more than 'max_user_connections' active connections
        ];

        $connectionErrorMessages = [
            'connection refused',
            'server has gone away',
            'lost connection',
            'too many connections',
            'connection timeout',
            'can\'t connect to',
            'connection closed',
            'broken pipe'
        ];

        // Check error codes
        if (in_array($e->getCode(), $connectionErrorCodes)) {
            return true;
        }

        // Check error messages
        $message = strtolower($e->getMessage());
        foreach ($connectionErrorMessages as $errorMessage) {
            if (str_contains($message, $errorMessage)) {
                return true;
            }
        }

        return false;
    }
}