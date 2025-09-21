<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Security Exception for handling security-related errors
 */
class SecurityException extends Exception
{
    private string $operation;
    private array $context;
    
    public function __construct(
        string $message = 'Security violation detected',
        string $operation = 'security_check',
        int $code = 403,
        array $context = [],
        Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->operation = $operation;
        $this->context = $context;
        
        // Log the security exception immediately
        $this->logSecurityException();
    }

    /**
     * Get the operation that triggered this security exception
     *
     * @return string
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Get the context data for this security exception
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Log the security exception with detailed information
     */
    private function logSecurityException(): void
    {
        Log::critical('SECURITY EXCEPTION', [
            'message' => $this->getMessage(),
            'operation' => $this->operation,
            'code' => $this->getCode(),
            'context' => $this->context,
            'ip' => request()->ip() ?? 'unknown',
            'user_agent' => request()->header('User-Agent') ?? 'unknown',
            'request_uri' => request()->getRequestUri() ?? 'unknown',
            'request_method' => request()->method() ?? 'unknown',
            'timestamp' => now()->toISOString(),
            'severity' => 'CRITICAL',
            'file' => $this->getFile(),
            'line' => $this->getLine()
        ]);
    }

    /**
     * Render the exception as an HTTP response
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Request blocked for security reasons',
            'error' => [
                'type' => 'SecurityException',
                'operation' => $this->operation,
                'code' => $this->getCode(),
                'timestamp' => now()->toISOString()
            ],
            'meta' => [
                'request_id' => request()->header('X-Request-ID') ?? uniqid(),
                'security_level' => 'high'
            ]
        ], $this->getCode());
    }

    /**
     * Check if this is a SQL injection related security exception
     *
     * @return bool
     */
    public function isSqlInjectionRelated(): bool
    {
        return strpos($this->operation, 'sql') !== false || 
               strpos(strtolower($this->getMessage()), 'sql') !== false;
    }

    /**
     * Check if this is an XSS related security exception
     *
     * @return bool
     */
    public function isXssRelated(): bool
    {
        return strpos($this->operation, 'xss') !== false || 
               strpos(strtolower($this->getMessage()), 'xss') !== false;
    }

    /**
     * Get security threat level
     *
     * @return string
     */
    public function getThreatLevel(): string
    {
        switch ($this->getCode()) {
            case 403:
                return 'HIGH';
            case 400:
                return 'MEDIUM';
            case 422:
                return 'LOW';
            default:
                return 'CRITICAL';
        }
    }
}