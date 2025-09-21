<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SqlInjectionProtectionService;
use App\Services\InputSanitizationService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * SQL Injection Protection Middleware
 * 
 * Middleware to protect all incoming requests from SQL injection attacks
 */
class SqlInjectionProtectionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Check if this is an API request that needs protection
            if ($this->shouldProtectRequest($request)) {
                $this->validateRequest($request);
            }
        } catch (\Exception $e) {
            // Log the error but don't break the application
            Log::error('SQL injection protection middleware error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Continue processing the request even if protection fails
            // This ensures the application remains functional
        }

        return $next($request);
    }

    /**
     * Determine if the request should be protected
     *
     * @param Request $request
     * @return bool
     */
    private function shouldProtectRequest(Request $request): bool
    {
        // Protect all API routes and sensitive routes
        $protectedPaths = [
            '/api/',
            '/admin/',
            '/tasks/',
            '/logs/'
        ];

        $path = $request->getPathInfo();
        
        foreach ($protectedPaths as $protectedPath) {
            if (strpos($path, $protectedPath) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate the request for SQL injection attempts
     *
     * @param Request $request
     * @throws \App\Exceptions\SecurityException
     */
    private function validateRequest(Request $request): void
    {
        try {
            $suspiciousInputs = [];

            // Check query parameters
            foreach ($request->query() as $key => $value) {
                if ($this->isInputSuspicious($value, "query.{$key}")) {
                    $suspiciousInputs["query.{$key}"] = $value;
                }
            }

            // Check POST/PUT/PATCH data
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
                $this->validateRequestBody($request, $suspiciousInputs);
            }

            // Check headers for injection attempts
            $this->validateHeaders($request, $suspiciousInputs);

            // If suspicious inputs are found, handle the security threat
            if (!empty($suspiciousInputs)) {
                $this->handleSecurityThreat($request, $suspiciousInputs);
            }
        } catch (\Exception $e) {
            Log::error('Error in SQL injection validation', [
                'error' => $e->getMessage(),
                'request_uri' => $request->getRequestUri()
            ]);
            // Don't throw the exception to avoid breaking the app
        }
    }

    /**
     * Validate request body for SQL injection
     *
     * @param Request $request
     * @param array &$suspiciousInputs
     */
    private function validateRequestBody(Request $request, array &$suspiciousInputs): void
    {
        $data = $request->all();
        $this->scanArrayForSqlInjection($data, $suspiciousInputs, 'body');
    }

    /**
     * Validate headers for SQL injection attempts
     *
     * @param Request $request
     * @param array &$suspiciousInputs
     */
    private function validateHeaders(Request $request, array &$suspiciousInputs): void
    {
        $headersToCheck = [
            'User-Agent',
            'Referer',
            'X-Forwarded-For',
            'X-Real-IP',
            'Authorization'
        ];

        foreach ($headersToCheck as $header) {
            $value = $request->header($header);
            if ($value && $this->isInputSuspicious($value, "header.{$header}")) {
                $suspiciousInputs["header.{$header}"] = $value;
            }
        }
    }

    /**
     * Recursively scan array for SQL injection patterns
     *
     * @param array $data
     * @param array &$suspiciousInputs
     * @param string $prefix
     */
    private function scanArrayForSqlInjection(array $data, array &$suspiciousInputs, string $prefix): void
    {
        foreach ($data as $key => $value) {
            $currentPath = "{$prefix}.{$key}";

            if (is_array($value)) {
                $this->scanArrayForSqlInjection($value, $suspiciousInputs, $currentPath);
            } elseif ($this->isInputSuspicious($value, $currentPath)) {
                $suspiciousInputs[$currentPath] = $value;
            }
        }
    }

    /**
     * Check if input is suspicious - simplified version without service dependencies
     *
     * @param mixed $value
     * @param string $context
     * @return bool
     */
    private function isInputSuspicious($value, string $context): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            // Basic SQL injection detection patterns
            $sqlPatterns = [
                // SQL keywords with common injection patterns
                '/\b(select|union|insert|update|delete|drop|create|alter|truncate|exec|execute)\s+/i',
                // Comment patterns
                '/--|\#|\/\*|\*\//',
                // Common injection attempts
                '/\'\s*(or|and)\s*\'/i',
                '/\'\s*(;|\|)/i',
                // SQL functions
                '/\b(concat|char|ascii|substring|length|database|version|user)\s*\(/i',
                // Hex encoding
                '/0x[0-9a-f]+/i',
            ];

            foreach ($sqlPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Error checking suspicious input', [
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            return false; // Assume safe if we can't check
        }
    }

    /**
     * Handle security threat by logging and potentially blocking
     *
     * @param Request $request
     * @param array $suspiciousInputs
     * @throws \App\Exceptions\SecurityException
     */
    private function handleSecurityThreat(Request $request, array $suspiciousInputs): void
    {
        // Log the security threat
        Log::critical('SQL INJECTION PROTECTION: Malicious request detected', [
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'suspicious_inputs' => array_keys($suspiciousInputs),
            'input_count' => count($suspiciousInputs),
            'timestamp' => now()->toISOString(),
            'severity' => 'CRITICAL'
        ]);

        // Count the number of suspicious patterns
        $threatLevel = count($suspiciousInputs);
        
        // Block requests with high threat levels
        if ($threatLevel >= 3) {
            Log::emergency('BLOCKING HIGHLY SUSPICIOUS REQUEST', [
                'threat_level' => $threatLevel,
                'ip' => $request->ip(),
                'suspicious_inputs' => $suspiciousInputs
            ]);

            throw new \App\Exceptions\SecurityException(
                'Request blocked due to suspicious content',
                'sql_injection_protection',
                403
            );
        }

        // For medium threat levels, sanitize the input but continue
        if ($threatLevel >= 1) {
            Log::warning('Sanitizing suspicious request', [
                'threat_level' => $threatLevel,
                'ip' => $request->ip()
            ]);

            $this->sanitizeRequestData($request);
        }
    }

    /**
     * Sanitize suspicious request data
     *
     * @param Request $request
     */
    private function sanitizeRequestData(Request $request): void
    {
        // Sanitize query parameters
        $queryParams = $request->query();
        foreach ($queryParams as $key => $value) {
            $queryParams[$key] = $this->sqlProtectionService->sanitizeInput($value, "query.{$key}");
        }
        $request->query->replace($queryParams);

        // Sanitize request body for POST/PUT/PATCH
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $bodyData = $request->all();
            $sanitizedData = $this->sanitizeArrayRecursive($bodyData, 'body');
            $request->replace($sanitizedData);
        }
    }

    /**
     * Recursively sanitize array data
     *
     * @param array $data
     * @param string $context
     * @return array
     */
    private function sanitizeArrayRecursive(array $data, string $context): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArrayRecursive($value, "{$context}.{$key}");
            } else {
                $sanitized[$key] = $this->sqlProtectionService->sanitizeInput($value, "{$context}.{$key}");
            }
        }

        return $sanitized;
    }
}