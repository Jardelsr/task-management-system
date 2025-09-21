<?php

namespace App\Exceptions;

use Exception;
use Carbon\Carbon;

/**
 * Rate Limiting Exception
 * 
 * Thrown when API rate limits are exceeded or concurrent requests are detected
 */
class RateLimitException extends Exception
{
    /**
     * @var string
     */
    private string $limitType;

    /**
     * @var array
     */
    private array $limitDetails;

    /**
     * @var int
     */
    private int $retryAfter;

    /**
     * Create a new rate limit exception
     *
     * @param string $message
     * @param string $limitType
     * @param array $limitDetails
     * @param int $retryAfter
     * @param int $code
     */
    public function __construct(
        string $message = 'Rate limit exceeded',
        string $limitType = 'request_limit',
        array $limitDetails = [],
        int $retryAfter = 60,
        int $code = 429
    ) {
        parent::__construct($message, $code);
        
        $this->limitType = $limitType;
        $this->limitDetails = $limitDetails;
        $this->retryAfter = $retryAfter;
    }

    /**
     * Create rate limit exceeded exception
     *
     * @param string $key
     * @param int $currentCount
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @param int $retryAfter
     * @return static
     */
    public static function forRateLimit(
        string $key,
        int $currentCount,
        int $maxAttempts,
        int $windowSeconds,
        int $retryAfter = null
    ): self {
        return new self(
            "Rate limit of {$maxAttempts} requests per {$windowSeconds} seconds exceeded",
            'rate_limit',
            [
                'limit_key' => $key,
                'current_count' => $currentCount,
                'max_attempts' => $maxAttempts,
                'window_seconds' => $windowSeconds,
                'window_type' => 'sliding'
            ],
            $retryAfter ?? $windowSeconds
        );
    }

    /**
     * Create concurrent request exception
     *
     * @param string $operationKey
     * @param int $retryAfter
     * @return static
     */
    public static function forConcurrentOperation(
        string $operationKey,
        int $retryAfter = 5
    ): self {
        return new self(
            'Concurrent operation detected. Please wait and try again.',
            'concurrent_operation',
            [
                'operation_key' => $operationKey,
                'guidance' => 'Wait for the current operation to complete before retrying'
            ],
            $retryAfter,
            409 // Conflict status code for concurrent operations
        );
    }

    /**
     * Create IP-based rate limit exception
     *
     * @param string $ipAddress
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @param int $retryAfter
     * @return static
     */
    public static function forIpRateLimit(
        string $ipAddress,
        int $maxAttempts,
        int $windowSeconds,
        int $retryAfter = null
    ): self {
        return new self(
            "Too many requests from IP address {$ipAddress}",
            'ip_rate_limit',
            [
                'ip_address' => $ipAddress,
                'max_attempts' => $maxAttempts,
                'window_seconds' => $windowSeconds,
                'limit_scope' => 'per_ip'
            ],
            $retryAfter ?? $windowSeconds
        );
    }

    /**
     * Create user-based rate limit exception
     *
     * @param string $userId
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @param int $retryAfter
     * @return static
     */
    public static function forUserRateLimit(
        string $userId,
        int $maxAttempts,
        int $windowSeconds,
        int $retryAfter = null
    ): self {
        return new self(
            "Too many requests from user {$userId}",
            'user_rate_limit',
            [
                'user_id' => $userId,
                'max_attempts' => $maxAttempts,
                'window_seconds' => $windowSeconds,
                'limit_scope' => 'per_user'
            ],
            $retryAfter ?? $windowSeconds
        );
    }

    /**
     * Create endpoint-based rate limit exception
     *
     * @param string $endpoint
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @param int $retryAfter
     * @return static
     */
    public static function forEndpointRateLimit(
        string $endpoint,
        int $maxAttempts,
        int $windowSeconds,
        int $retryAfter = null
    ): self {
        return new self(
            "Too many requests to endpoint {$endpoint}",
            'endpoint_rate_limit',
            [
                'endpoint' => $endpoint,
                'max_attempts' => $maxAttempts,
                'window_seconds' => $windowSeconds,
                'limit_scope' => 'per_endpoint'
            ],
            $retryAfter ?? $windowSeconds
        );
    }

    /**
     * Create bulk operation rate limit exception
     *
     * @param int $itemCount
     * @param int $maxItems
     * @param int $retryAfter
     * @return static
     */
    public static function forBulkOperation(
        int $itemCount,
        int $maxItems,
        int $retryAfter = 60
    ): self {
        return new self(
            "Bulk operation size limit exceeded: {$itemCount} items (max: {$maxItems})",
            'bulk_operation_limit',
            [
                'item_count' => $itemCount,
                'max_items' => $maxItems,
                'guidance' => 'Split the operation into smaller batches'
            ],
            $retryAfter,
            413 // Payload Too Large
        );
    }

    /**
     * Get the limit type
     *
     * @return string
     */
    public function getLimitType(): string
    {
        return $this->limitType;
    }

    /**
     * Get limit details
     *
     * @return array
     */
    public function getLimitDetails(): array
    {
        return $this->limitDetails;
    }

    /**
     * Get retry after seconds
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Get error details for API response
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        $response = [
            'success' => false,
            'error' => 'Rate limit exceeded',
            'message' => $this->getMessage(),
            'code' => $this->getLimitTypeCode(),
            'retry_after' => $this->retryAfter,
            'timestamp' => Carbon::now()->toISOString()
        ];

        // Add details based on limit type
        switch ($this->limitType) {
            case 'rate_limit':
                $response['details'] = [
                    'limit_type' => 'request_rate_limit',
                    'max_requests' => $this->limitDetails['max_attempts'] ?? null,
                    'window_seconds' => $this->limitDetails['window_seconds'] ?? null,
                    'current_count' => $this->limitDetails['current_count'] ?? null
                ];
                break;

            case 'concurrent_operation':
                $response['details'] = [
                    'limit_type' => 'concurrent_operation',
                    'operation_key' => $this->limitDetails['operation_key'] ?? null,
                    'guidance' => $this->limitDetails['guidance'] ?? null
                ];
                break;

            case 'ip_rate_limit':
                $response['details'] = [
                    'limit_type' => 'ip_rate_limit',
                    'max_requests' => $this->limitDetails['max_attempts'] ?? null,
                    'window_seconds' => $this->limitDetails['window_seconds'] ?? null,
                    'scope' => 'per_ip_address'
                ];
                break;

            case 'user_rate_limit':
                $response['details'] = [
                    'limit_type' => 'user_rate_limit',
                    'max_requests' => $this->limitDetails['max_attempts'] ?? null,
                    'window_seconds' => $this->limitDetails['window_seconds'] ?? null,
                    'scope' => 'per_user'
                ];
                break;

            case 'endpoint_rate_limit':
                $response['details'] = [
                    'limit_type' => 'endpoint_rate_limit',
                    'endpoint' => $this->limitDetails['endpoint'] ?? null,
                    'max_requests' => $this->limitDetails['max_attempts'] ?? null,
                    'window_seconds' => $this->limitDetails['window_seconds'] ?? null
                ];
                break;

            case 'bulk_operation_limit':
                $response['details'] = [
                    'limit_type' => 'bulk_operation_limit',
                    'item_count' => $this->limitDetails['item_count'] ?? null,
                    'max_items' => $this->limitDetails['max_items'] ?? null,
                    'guidance' => $this->limitDetails['guidance'] ?? null
                ];
                break;

            default:
                $response['details'] = $this->limitDetails;
        }

        return $response;
    }

    /**
     * Get HTTP headers for rate limiting response
     *
     * @return array
     */
    public function getHttpHeaders(): array
    {
        $headers = [
            'Retry-After' => $this->retryAfter,
            'X-RateLimit-Type' => $this->limitType,
        ];

        // Add specific headers based on limit type
        if (isset($this->limitDetails['max_attempts'])) {
            $headers['X-RateLimit-Limit'] = $this->limitDetails['max_attempts'];
        }

        if (isset($this->limitDetails['current_count'])) {
            $headers['X-RateLimit-Remaining'] = max(
                0, 
                ($this->limitDetails['max_attempts'] ?? 0) - $this->limitDetails['current_count']
            );
        }

        if (isset($this->limitDetails['window_seconds'])) {
            $headers['X-RateLimit-Window'] = $this->limitDetails['window_seconds'];
            $headers['X-RateLimit-Reset'] = time() + $this->limitDetails['window_seconds'];
        }

        return $headers;
    }

    /**
     * Get error code based on limit type
     *
     * @return string
     */
    private function getLimitTypeCode(): string
    {
        return match ($this->limitType) {
            'rate_limit' => 'RATE_LIMIT_EXCEEDED',
            'concurrent_operation' => 'CONCURRENT_OPERATION_DETECTED',
            'ip_rate_limit' => 'IP_RATE_LIMIT_EXCEEDED',
            'user_rate_limit' => 'USER_RATE_LIMIT_EXCEEDED',
            'endpoint_rate_limit' => 'ENDPOINT_RATE_LIMIT_EXCEEDED',
            'bulk_operation_limit' => 'BULK_OPERATION_LIMIT_EXCEEDED',
            default => 'RATE_LIMIT_ERROR'
        };
    }

    /**
     * Check if this is a retryable rate limit
     *
     * @return bool
     */
    public function isRetryable(): bool
    {
        return $this->limitType !== 'bulk_operation_limit';
    }

    /**
     * Get suggested retry delay with jitter
     *
     * @param float $jitterFactor
     * @return int
     */
    public function getSuggestedRetryDelay(float $jitterFactor = 0.1): int
    {
        $baseDelay = $this->retryAfter;
        $jitter = (int)($baseDelay * $jitterFactor * (mt_rand() / mt_getrandmax() - 0.5));
        
        return max(1, $baseDelay + $jitter);
    }
}