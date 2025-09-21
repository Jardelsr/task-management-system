<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Throwable;

class ErrorLoggingService
{
    /**
     * Log error with comprehensive context
     */
    public static function logError(Throwable $exception, Request $request = null, array $additionalContext = []): void
    {
        try {
            $context = self::buildErrorContext($exception, $request, $additionalContext);
            
            // Try to use configured channels, fallback to direct file logging
            try {
                Log::channel($channel)->error($exception->getMessage(), $context);
                
                // Also log to main error log for centralized monitoring
                if ($channel !== 'error_file') {
                    Log::channel('error_file')->error($exception->getMessage(), $context);
                }
            } catch (\Throwable $logException) {
                // Fallback to direct file logging
                self::logToFile('error', $exception->getMessage(), $context);
            }
            
        } catch (\Throwable $loggingException) {
            // Final fallback
            error_log("ErrorLoggingService failed: " . $loggingException->getMessage());
            error_log("Original error: " . $exception->getMessage());
        }
    }

    /**
     * Log validation error
     */
    public static function logValidationError(array $errors, Request $request = null, array $additionalContext = []): void
    {
        try {
            $context = array_merge([
                'validation_errors' => $errors,
                'request_method' => $request?->method() ?? 'UNKNOWN',
                'request_path' => $request?->path() ?? 'UNKNOWN',
                'request_data' => self::sanitizeRequestData($request?->all() ?? []),
                'timestamp' => Carbon::now()->toISOString(),
                'user_agent' => $request?->userAgent(),
                'ip_address' => $request?->ip(),
            ], $additionalContext);

            try {
                Log::channel('validation_errors')->warning('Validation failed', $context);
            } catch (\Throwable $logException) {
                self::logToFile('validation_errors', 'Validation failed', $context);
            }
            
        } catch (\Throwable $loggingException) {
            error_log("Failed to log validation error: " . $loggingException->getMessage());
        }
    }

    /**
     * Log database error
     */
    public static function logDatabaseError(Throwable $exception, string $operation = null, array $additionalContext = []): void
    {
        try {
            $context = array_merge([
                'operation' => $operation,
                'error_type' => 'database_error',
                'exception_class' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'timestamp' => Carbon::now()->toISOString(),
            ], $additionalContext);

            try {
                Log::channel('database_errors')->error('Database operation failed', $context);
                Log::channel('error_file')->error('Database operation failed', $context);
            } catch (\Throwable $logException) {
                self::logToFile('database_errors', 'Database operation failed', $context);
            }
            
        } catch (\Throwable $loggingException) {
            error_log("Failed to log database error: " . $loggingException->getMessage());
        }
    }

    /**
     * Log task operation
     */
    public static function logTaskOperation(string $operation, $taskId = null, array $data = [], string $level = 'info'): void
    {
        try {
            $context = [
                'operation' => $operation,
                'task_id' => $taskId,
                'data' => self::sanitizeData($data),
                'timestamp' => Carbon::now()->toISOString(),
                'memory_usage' => memory_get_usage(true),
            ];

            try {
                Log::channel('task_operations')->{$level}("Task {$operation}", $context);
            } catch (\Throwable $logException) {
                self::logToFile('task_operations', "Task {$operation}", $context);
            }
            
        } catch (\Throwable $loggingException) {
            error_log("Failed to log task operation: " . $loggingException->getMessage());
        }
    }

    /**
     * Log API request/response
     */
    public static function logApiOperation(Request $request, $response = null, string $level = 'info', array $additionalContext = []): void
    {
        try {
            $context = array_merge([
                'method' => $request->method(),
                'path' => $request->path(),
                'query_params' => $request->query(),
                'request_size' => strlen(json_encode($request->all())),
                'response_size' => is_object($response) && method_exists($response, 'getContent') 
                    ? strlen($response->getContent()) 
                    : null,
                'status_code' => is_object($response) && method_exists($response, 'getStatusCode') 
                    ? $response->getStatusCode() 
                    : null,
                'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
                'timestamp' => Carbon::now()->toISOString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], $additionalContext);

            Log::channel('daily')->{$level}('API Operation', $context);
            
        } catch (\Throwable $loggingException) {
            error_log("Failed to log API operation: " . $loggingException->getMessage());
        }
    }

    /**
     * Log performance metrics
     */
    public static function logPerformanceMetrics(string $operation, float $executionTime, array $additionalContext = []): void
    {
        try {
            $context = array_merge([
                'operation' => $operation,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'timestamp' => Carbon::now()->toISOString(),
            ], $additionalContext);

            // Log performance warnings for slow operations
            if ($executionTime > 1.0) {
                Log::channel('daily')->warning('Slow operation detected', $context);
            } else {
                Log::channel('daily')->info('Performance metric', $context);
            }
            
        } catch (\Throwable $loggingException) {
            error_log("Failed to log performance metrics: " . $loggingException->getMessage());
        }
    }

    /**
     * Build comprehensive error context
     */
    private static function buildErrorContext(Throwable $exception, Request $request = null, array $additionalContext = []): array
    {
        $context = [
            'exception_class' => get_class($exception),
            'error_message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'timestamp' => Carbon::now()->toISOString(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];

        // Add stack trace if configured
        if (config('logging.error_logging.include_stack_trace', true)) {
            $context['stack_trace'] = $exception->getTraceAsString();
        }

        // Add request context if available and configured
        if ($request && config('logging.error_logging.include_request_data', true)) {
            $context['request'] = [
                'method' => $request->method(),
                'path' => $request->path(),
                'query_params' => $request->query(),
                'request_data' => self::sanitizeRequestData($request->all()),
                'headers' => self::sanitizeHeaders($request->headers->all()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];
        }

        return array_merge($context, $additionalContext);
    }

    /**
     * Determine appropriate log channel based on exception type
     */
    private static function determineLogChannel(Throwable $exception): string
    {
        $exceptionClass = get_class($exception);

        if (str_contains($exceptionClass, 'Database') || str_contains($exceptionClass, 'QueryException')) {
            return 'database_errors';
        }

        if (str_contains($exceptionClass, 'Validation')) {
            return 'validation_errors';
        }

        return 'error_file';
    }

    /**
     * Sanitize request data by removing sensitive information
     */
    private static function sanitizeRequestData(array $data): array
    {
        $sensitiveFields = config('logging.error_logging.sensitive_fields', ['password', 'token', 'api_key', 'secret']);
        $maxSize = config('logging.error_logging.max_context_size', 1000);

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        $jsonData = json_encode($data);
        if (strlen($jsonData) > $maxSize) {
            return ['_truncated' => 'Data too large to log completely', '_size' => strlen($jsonData)];
        }

        return $data;
    }

    /**
     * Sanitize headers by removing sensitive information
     */
    private static function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key'];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }

    /**
     * Sanitize general data
     */
    private static function sanitizeData(array $data): array
    {
        return self::sanitizeRequestData($data);
    }

    /**
     * Direct file logging fallback
     */
    private static function logToFile(string $type, string $message, array $context = []): void
    {
        try {
            $logFile = storage_path("logs/{$type}-" . date('Y-m-d') . '.log');
            $timestamp = date('Y-m-d H:i:s');
            $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
            $logEntry = "[{$timestamp}] {$type}.ERROR: {$message}{$contextStr}" . PHP_EOL;
            
            // Ensure directory exists
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            
        } catch (\Throwable $e) {
            error_log("Direct file logging failed: " . $e->getMessage());
        }
    }
}