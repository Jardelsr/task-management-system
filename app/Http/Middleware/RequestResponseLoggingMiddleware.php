<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ErrorLoggingService;
use Throwable;

class RequestResponseLoggingMiddleware
{
    /**
     * Handle an incoming request and log the response.
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        try {
            // Process the request
            $response = $next($request);

            // Calculate execution time
            $executionTime = microtime(true) - $startTime;

            // Log successful API operations
            $this->logSuccessfulOperation($request, $response, $executionTime);

            return $response;

        } catch (Throwable $exception) {
            // Calculate execution time for failed requests
            $executionTime = microtime(true) - $startTime;

            // Log failed operations
            $this->logFailedOperation($request, $exception, $executionTime);

            // Re-throw the exception to let the handler deal with it
            throw $exception;
        }
    }

    /**
     * Log successful API operations
     */
    private function logSuccessfulOperation(Request $request, $response, float $executionTime): void
    {
        try {
            $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
            $level = $statusCode >= 400 ? 'warning' : 'info';

            ErrorLoggingService::logApiOperation($request, $response, $level, [
                'execution_time_ms' => round($executionTime * 1000, 2),
                'success' => true,
                'middleware' => 'RequestResponseLoggingMiddleware'
            ]);

            // Log performance metrics for slow operations
            if ($executionTime > 1.0) {
                ErrorLoggingService::logPerformanceMetrics(
                    $request->method() . ' ' . $request->path(),
                    $executionTime,
                    [
                        'status_code' => $statusCode,
                        'slow_operation' => true
                    ]
                );
            }

        } catch (Throwable $loggingException) {
            // Fallback logging to prevent disrupting the request
            error_log("Failed to log successful operation: " . $loggingException->getMessage());
        }
    }

    /**
     * Log failed API operations
     */
    private function logFailedOperation(Request $request, Throwable $exception, float $executionTime): void
    {
        try {
            ErrorLoggingService::logError($exception, $request, [
                'execution_time_ms' => round($executionTime * 1000, 2),
                'context' => 'middleware_error_capture',
                'middleware' => 'RequestResponseLoggingMiddleware'
            ]);

            // Always log performance metrics for failed operations
            ErrorLoggingService::logPerformanceMetrics(
                $request->method() . ' ' . $request->path(),
                $executionTime,
                [
                    'exception' => get_class($exception),
                    'failed_operation' => true
                ]
            );

        } catch (Throwable $loggingException) {
            // Fallback logging to prevent disrupting the request
            error_log("Failed to log failed operation: " . $loggingException->getMessage());
            error_log("Original exception: " . $exception->getMessage());
        }
    }
}