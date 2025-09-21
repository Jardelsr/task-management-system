<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use App\Traits\ErrorResponseTrait;
use App\Traits\SuccessResponseTrait;
use App\Traits\SecurityErrorHandlingTrait;
use App\Traits\InputValidationTrait;
use App\Exceptions\DatabaseException;
use App\Exceptions\TaskOperationException;
use App\Exceptions\TaskValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Base Controller with comprehensive error handling
 */
class Controller extends BaseController
{
    use ErrorResponseTrait, SuccessResponseTrait, SecurityErrorHandlingTrait, InputValidationTrait;

    /**
     * Maximum retry attempts for database operations
     */
    protected const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Default timeout for operations (seconds)
     */
    protected const DEFAULT_TIMEOUT = 30;

    /**
     * Handle database operation with retry logic
     *
     * @param callable $operation
     * @param string $operationName
     * @param int $maxRetries
     * @return mixed
     * @throws DatabaseException
     */
    protected function handleDatabaseOperation(
        callable $operation, 
        string $operationName, 
        int $maxRetries = self::MAX_RETRY_ATTEMPTS
    ) {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return DB::transaction($operation);
            } catch (QueryException $e) {
                $lastException = $e;
                $attempt++;

                // Check if it's a retryable error
                if (!$this->isRetryableError($e) || $attempt >= $maxRetries) {
                    break;
                }

                // Wait before retry with exponential backoff
                usleep(pow(2, $attempt) * 100000); // 200ms, 400ms, 800ms
                
                Log::warning("Database operation retry", [
                    'operation' => $operationName,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
            } catch (\Exception $e) {
                // Allow validation exceptions to pass through without wrapping
                if ($e instanceof TaskValidationException) {
                    throw $e;
                }
                
                // Non-database exceptions are not retryable
                throw new DatabaseException(
                    "Database operation '{$operationName}' failed: " . $e->getMessage(),
                    $operationName,
                    ['error_type' => get_class($e)]
                );
            }
        }

        throw new DatabaseException(
            "Database operation '{$operationName}' failed after {$maxRetries} attempts",
            $operationName,
            [
                'attempts' => $maxRetries,
                'last_error' => $lastException->getMessage(),
                'error_code' => $lastException->getCode()
            ]
        );
    }

    /**
     * Check if database error is retryable
     *
     * @param QueryException $e
     * @return bool
     */
    protected function isRetryableError(QueryException $e): bool
    {
        $retryableCodes = [
            1040, // Too many connections
            1205, // Lock wait timeout
            1213, // Deadlock found
            2006, // MySQL server has gone away
            2013, // Lost connection to MySQL server during query
        ];

        return in_array($e->getCode(), $retryableCodes) ||
               str_contains($e->getMessage(), 'server has gone away') ||
               str_contains($e->getMessage(), 'Connection refused');
    }

    /**
     * Handle operation with timeout
     *
     * @param callable $operation
     * @param string $operationName
     * @param int $timeout
     * @return mixed
     * @throws TaskOperationException
     */
    protected function handleOperationWithTimeout(
        callable $operation, 
        string $operationName, 
        int $timeout = self::DEFAULT_TIMEOUT
    ) {
        $startTime = microtime(true);
        
        try {
            // Set time limit for the operation
            set_time_limit($timeout);
            
            $result = $operation();
            
            $executionTime = microtime(true) - $startTime;
            
            // Log slow operations
            if ($executionTime > ($timeout * 0.8)) {
                Log::warning("Slow operation detected", [
                    'operation' => $operationName,
                    'execution_time' => $executionTime,
                    'timeout_threshold' => $timeout * 0.8
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            // Check if it's a timeout
            if ($executionTime >= $timeout) {
                throw new TaskOperationException(
                    "Operation '{$operationName}' timed out after {$timeout} seconds",
                    $operationName,
                    null,
                    ['execution_time' => $executionTime, 'timeout' => $timeout]
                );
            }
            
            // Re-throw other exceptions
            throw $e;
        }
    }

    /**
     * Handle memory-intensive operation
     *
     * @param callable $operation
     * @param string $operationName
     * @param int $memoryLimit Memory limit in MB
     * @return mixed
     * @throws TaskOperationException
     */
    protected function handleMemoryIntensiveOperation(
        callable $operation,
        string $operationName,
        int $memoryLimit = 128
    ) {
        $initialMemory = memory_get_usage(true);
        $memoryLimitBytes = $memoryLimit * 1024 * 1024;
        
        try {
            $result = $operation();
            
            $finalMemory = memory_get_usage(true);
            $memoryUsed = $finalMemory - $initialMemory;
            
            // Log high memory usage
            if ($memoryUsed > ($memoryLimitBytes * 0.8)) {
                Log::warning("High memory usage detected", [
                    'operation' => $operationName,
                    'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                    'memory_limit_mb' => $memoryLimit
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $finalMemory = memory_get_usage(true);
            $memoryUsed = $finalMemory - $initialMemory;
            
            // Check if memory limit was exceeded
            if ($memoryUsed > $memoryLimitBytes) {
                throw new TaskOperationException(
                    "Operation '{$operationName}' exceeded memory limit of {$memoryLimit}MB",
                    $operationName,
                    null,
                    [
                        'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                        'memory_limit_mb' => $memoryLimit
                    ]
                );
            }
            
            throw $e;
        }
    }

    /**
     * Validate request size and structure
     *
     * @param array $data
     * @param int $maxSize Maximum data size in KB
     * @param int $maxDepth Maximum array depth
     * @throws TaskOperationException
     */
    protected function validateRequestSize(
        array $data, 
        int $maxSize = 1024,
        int $maxDepth = 10
    ): void {
        // Check data size
        $dataSize = strlen(json_encode($data)) / 1024;
        if ($dataSize > $maxSize) {
            throw new TaskOperationException(
                "Request data too large: {$dataSize}KB exceeds limit of {$maxSize}KB",
                'request_validation',
                null,
                ['data_size_kb' => round($dataSize, 2), 'max_size_kb' => $maxSize]
            );
        }

        // Check array depth
        $currentDepth = $this->getArrayDepth($data);
        if ($currentDepth > $maxDepth) {
            throw new TaskOperationException(
                "Request data structure too deep: {$currentDepth} levels exceed limit of {$maxDepth}",
                'request_validation',
                null,
                ['current_depth' => $currentDepth, 'max_depth' => $maxDepth]
            );
        }
    }

    /**
     * Get array depth recursively
     *
     * @param array $array
     * @return int
     */
    private function getArrayDepth(array $array): int
    {
        $maxDepth = 1;

        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->getArrayDepth($value) + 1;
                $maxDepth = max($maxDepth, $depth);
            }
        }

        return $maxDepth;
    }

    /**
     * Handle graceful degradation for non-critical operations
     *
     * @param callable $operation
     * @param callable|null $fallback
     * @param string $operationName
     * @return mixed
     */
    protected function handleWithFallback(
        callable $operation,
        ?callable $fallback,
        string $operationName
    ) {
        try {
            return $operation();
        } catch (\Exception $e) {
            Log::warning("Operation failed, using fallback", [
                'operation' => $operationName,
                'error' => $e->getMessage(),
                'has_fallback' => $fallback !== null
            ]);

            if ($fallback) {
                try {
                    return $fallback();
                } catch (\Exception $fallbackException) {
                    Log::error("Fallback also failed", [
                        'operation' => $operationName,
                        'original_error' => $e->getMessage(),
                        'fallback_error' => $fallbackException->getMessage()
                    ]);
                }
            }

            // If no fallback or fallback failed, re-throw original exception
            throw $e;
        }
    }

    /**
     * Handle concurrent request detection
     *
     * @param string $operationKey
     * @param callable $operation
     * @param string $operationName
     * @return mixed
     * @throws TaskOperationException
     */
    protected function handleConcurrentOperation(
        string $operationKey,
        callable $operation,
        string $operationName
    ) {
        $lockKey = "operation_lock:{$operationKey}";
        $lockTimeout = 30; // seconds

        // Try to acquire lock
        if (!$this->acquireLock($lockKey, $lockTimeout)) {
            throw new TaskOperationException(
                "Concurrent operation detected for '{$operationName}'. Please try again.",
                $operationName,
                null,
                ['operation_key' => $operationKey, 'lock_timeout' => $lockTimeout]
            );
        }

        try {
            return $operation();
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    /**
     * Simple file-based locking mechanism for Lumen
     *
     * @param string $lockKey
     * @param int $timeout
     * @return bool
     */
    private function acquireLock(string $lockKey, int $timeout): bool
    {
        $lockFile = storage_path("locks/" . md5($lockKey) . ".lock");
        $lockDir = dirname($lockFile);
        
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        $startTime = time();
        
        while (time() - $startTime < $timeout) {
            if (!file_exists($lockFile) || (time() - filemtime($lockFile)) > $timeout) {
                if (file_put_contents($lockFile, time()) !== false) {
                    return true;
                }
            }
            usleep(100000); // Wait 100ms
        }
        
        return false;
    }

    /**
     * Release lock
     *
     * @param string $lockKey
     */
    private function releaseLock(string $lockKey): void
    {
        $lockFile = storage_path("locks/" . md5($lockKey) . ".lock");
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    /**
     * Log operation performance metrics
     *
     * @param string $operation
     * @param float $executionTime
     * @param array $metrics
     */
    protected function logPerformanceMetrics(
        string $operation,
        float $executionTime,
        array $metrics = []
    ): void {
        $defaultMetrics = [
            'operation' => $operation,
            'execution_time_ms' => round($executionTime * 1000, 2),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'timestamp' => Carbon::now()->toISOString()
        ];

        Log::info('Performance metrics', array_merge($defaultMetrics, $metrics));
    }
}