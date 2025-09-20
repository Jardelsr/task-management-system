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
        // Handle custom task exceptions
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

        if ($exception instanceof LoggingException) {
            // For logging exceptions, return a generic error to avoid exposing internal details
            return $this->serverErrorResponse(
                'An error occurred while processing your request'
            );
        }

        // Handle Laravel validation exceptions
        if ($exception instanceof ValidationException) {
            return $this->validationErrorResponse(
                $exception->errors(),
                'The given data was invalid'
            );
        }

        // Handle model not found exceptions
        if ($exception instanceof ModelNotFoundException) {
            $model = class_basename($exception->getModel());
            return $this->notFoundResponse($model);
        }

        // Handle HTTP exceptions
        if ($exception instanceof NotFoundHttpException) {
            return $this->errorResponse(
                'Route not found',
                'The requested endpoint does not exist',
                404,
                [],
                'ROUTE_NOT_FOUND'
            );
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->errorResponse(
                'Method not allowed',
                'The requested HTTP method is not allowed for this endpoint',
                405,
                [],
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
            return $this->serverErrorResponse();
        }

        // In debug mode, let Laravel handle it to show detailed error information
        return parent::render($request, $exception);
    }
}