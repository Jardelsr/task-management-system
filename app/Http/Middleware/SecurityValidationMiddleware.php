<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Traits\SecurityErrorHandlingTrait;
use App\Traits\ErrorResponseTrait;
use App\Exceptions\RateLimitException;

/**
 * Comprehensive Security Middleware for Request Validation
 */
class SecurityValidationMiddleware
{
    use SecurityErrorHandlingTrait;
    use ErrorResponseTrait;

    /**
     * Handle an incoming request with comprehensive security validation
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws RateLimitException
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Basic security checks
            $this->performSecurityChecks($request);
            
            // Rate limiting based on IP
            $this->performRateLimiting($request);
            
            // Request size validation
            $this->validateRequestSize($request);
            
            // Content validation
            $this->validateRequestContent($request);
            
            $response = $next($request);
            
            // Add security headers to response
            return $this->addSecurityHeaders($response);
            
        } catch (RateLimitException $e) {
            // Re-throw rate limit exceptions to be handled by the exception handler
            throw $e;
        } catch (\Exception $e) {
            Log::warning('Security middleware error', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent()
            ]);
            
            return $this->securityErrorResponse('Request blocked by security policy');
        }
    }

    /**
     * Perform basic security checks
     *
     * @param Request $request
     * @throws \Exception
     */
    private function performSecurityChecks(Request $request): void
    {
        // Check for suspicious user agents
        $userAgent = $request->userAgent();
        if ($this->isSuspiciousUserAgent($userAgent)) {
            throw new \Exception('Suspicious user agent detected');
        }

        // Check for suspicious headers
        if ($this->hasSuspiciousHeaders($request)) {
            throw new \Exception('Suspicious headers detected');
        }

        // Check request method
        if (!in_array($request->method(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'])) {
            throw new \Exception('Invalid HTTP method');
        }
    }

    /**
     * Perform rate limiting checks
     *
     * @param Request $request
     * @throws RateLimitException
     */
    private function performRateLimiting(Request $request): void
    {
        $ip = $request->ip();
        
        // General rate limiting (per IP)
        $this->checkRateLimit(
            "general_ip:{$ip}",
            1000, // 1000 requests
            3600  // per hour
        );

        // More restrictive for certain endpoints
        if ($this->isSensitiveEndpoint($request)) {
            $this->checkRateLimit(
                "sensitive_ip:{$ip}",
                100, // 100 requests
                3600 // per hour
            );
        }

        // POST/PUT/PATCH requests (write operations)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->checkRateLimit(
                "write_ops_ip:{$ip}",
                200, // 200 write operations
                3600 // per hour
            );
        }
    }

    /**
     * Validate overall request size
     *
     * @param Request $request
     * @throws \Exception
     */
    private function validateRequestSize(Request $request): void
    {
        // Check content length
        $contentLength = $request->header('Content-Length', 0);
        $maxSize = 10 * 1024 * 1024; // 10MB

        if ($contentLength > $maxSize) {
            throw new \Exception('Request too large');
        }

        // Check query string length
        $queryString = $request->getQueryString();
        if ($queryString && strlen($queryString) > 2048) { // 2KB
            throw new \Exception('Query string too long');
        }
    }

    /**
     * Validate request content for security threats
     *
     * @param Request $request
     * @throws \Exception
     */
    private function validateRequestContent(Request $request): void
    {
        // Get all input data
        $allData = $request->all();
        
        if (!empty($allData)) {
            // Perform security validation
            $this->validateRequestSecurity($allData);
        }

        // Check for suspicious file uploads
        if ($request->hasFile('file')) {
            $files = $request->file('file');
            $files = is_array($files) ? $files : [$files];
            
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $this->validateFileUploadSecurity(
                        $file->getClientOriginalName(),
                        file_get_contents($file->getPathname())
                    );
                }
            }
        }
    }

    /**
     * Check if user agent is suspicious
     *
     * @param string|null $userAgent
     * @return bool
     */
    private function isSuspiciousUserAgent(?string $userAgent): bool
    {
        // Allow empty user agents for API clients (common in server-to-server communication)
        if (empty($userAgent)) {
            return false;
        }

        // Only block clearly malicious user agents, not legitimate tools
        $maliciousPatterns = [
            'sqlmap',           // SQL injection tool
            'nikto',            // Web vulnerability scanner
            'nmap',             // Network mapper (when used as user agent)
            'masscan',          // Mass IP port scanner
            'zmap',             // Fast internet-wide scanner
            'dirb',             // Web content scanner
            'dirbuster',        // Directory/file brute forcer
            'gobuster',         // Directory/DNS/VHost busting tool
            'wfuzz',            // Web application fuzzer
            'burp',             // Burp Suite scanner
            'acunetix',         // Web vulnerability scanner
            'nessus',           // Vulnerability scanner
            'openvas',          // Vulnerability scanner
            'w3af',             // Web application attack framework
            'havij',            // SQL injection tool
            'pangolin',         // SQL injection tool
            'bsqlbf',           // Blind SQL injection tool
        ];

        $userAgentLower = strtolower($userAgent);
        
        foreach ($maliciousPatterns as $pattern) {
            if (strpos($userAgentLower, $pattern) !== false) {
                return true;
            }
        }

        // Allow legitimate tools and API clients
        // curl, wget, postman, legitimate bots are now allowed
        return false;
    }

    /**
     * Check for suspicious headers
     *
     * @param Request $request
     * @return bool
     */
    private function hasSuspiciousHeaders(Request $request): bool
    {
        $suspiciousHeaders = [
            'X-Originating-IP',
            'X-Forwarded-Host',
            'X-Remote-IP'
        ];

        foreach ($suspiciousHeaders as $header) {
            if ($request->hasHeader($header)) {
                return true;
            }
        }

        // Check for unusual header values
        $xRealIp = $request->header('X-Real-IP');
        if ($xRealIp && !filter_var($xRealIp, FILTER_VALIDATE_IP)) {
            return true;
        }

        return false;
    }

    /**
     * Check if endpoint is sensitive
     *
     * @param Request $request
     * @return bool
     */
    private function isSensitiveEndpoint(Request $request): bool
    {
        $sensitivePatterns = [
            '/api/v1/tasks',
            '/api/v1/logs',
            '/admin',
            '/config'
        ];

        $path = $request->path();
        
        foreach ($sensitivePatterns as $pattern) {
            if (strpos($path, $pattern) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add security headers to response
     *
     * @param mixed $response
     * @return mixed
     */
    private function addSecurityHeaders($response)
    {
        if ($response instanceof JsonResponse || method_exists($response, 'header')) {
            // Prevent content type sniffing
            $response->header('X-Content-Type-Options', 'nosniff');
            
            // Prevent clickjacking
            $response->header('X-Frame-Options', 'DENY');
            
            // XSS Protection (deprecated but still useful for older browsers)
            $response->header('X-XSS-Protection', '1; mode=block');
            
            // Referrer policy
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
            
            // Comprehensive Content Security Policy
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "font-src 'self'",
                "connect-src 'self'",
                "media-src 'self'",
                "object-src 'none'",
                "child-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'"
            ]);
            $response->header('Content-Security-Policy', $csp);
            
            // HTTP Strict Transport Security (HSTS) - 1 year with includeSubDomains
            if (request()->isSecure()) {
                $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
            }
            
            // Permissions Policy (replaces Feature-Policy)
            $permissions = implode(', ', [
                'camera=()',
                'microphone=()',
                'geolocation=()',
                'payment=()',
                'usb=()',
                'magnetometer=()',
                'accelerometer=()',
                'gyroscope=()',
                'fullscreen=(self)',
                'display-capture=()'
            ]);
            $response->header('Permissions-Policy', $permissions);
            
            // Cross-Origin policies
            $response->header('Cross-Origin-Embedder-Policy', 'require-corp');
            $response->header('Cross-Origin-Opener-Policy', 'same-origin');
            $response->header('Cross-Origin-Resource-Policy', 'same-origin');
            
            // Server information hiding
            $response->header('Server', 'TaskManagementAPI/1.0');
            
            // Cache control for security
            if (request()->getPathInfo() !== '/health-check') {
                $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, private');
                $response->header('Pragma', 'no-cache');
                $response->header('Expires', '0');
            }
            
            // Add request ID for tracking
            $response->header('X-Request-ID', uniqid('req_', true));
            
            // Add rate limit info if available
            $response->header('X-Rate-Limit-Policy', 'IP-based limiting in effect');
            
            // Security timestamp
            $response->header('X-Security-Headers-Applied', date('c'));
        }

        return $response;
    }

    /**
     * Override checkRateLimit to throw RateLimitException instead of TaskOperationException
     *
     * @param string $key
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @throws RateLimitException
     */
    protected function checkRateLimit(
        string $key, 
        int $maxAttempts = 60, 
        int $windowSeconds = 60
    ): void {
        $cacheKey = "rate_limit:{$key}:" . floor(time() / $windowSeconds);
        $attempts = $this->getCacheValue($cacheKey, 0);

        if ($attempts >= $maxAttempts) {
            // Determine the rate limit type based on key
            if (strpos($key, 'general_ip:') === 0) {
                throw RateLimitException::forIpRateLimit(
                    request()->ip(),
                    $maxAttempts,
                    $windowSeconds
                );
            } elseif (strpos($key, 'sensitive_ip:') === 0) {
                throw RateLimitException::forEndpointRateLimit(
                    request()->path(),
                    $maxAttempts,
                    $windowSeconds
                );
            } elseif (strpos($key, 'write_ops_ip:') === 0) {
                throw RateLimitException::forRateLimit(
                    $key,
                    $attempts,
                    $maxAttempts,
                    $windowSeconds
                );
            } else {
                throw RateLimitException::forRateLimit(
                    $key,
                    $attempts,
                    $maxAttempts,
                    $windowSeconds
                );
            }
        }

        $this->setCacheValue($cacheKey, $attempts + 1, $windowSeconds);
    }

    /**
     * Simple cache implementation using files (for Lumen compatibility)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getCacheValue(string $key, $default = null)
    {
        $cacheFile = storage_path('cache/' . md5($key) . '.cache');
        
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            $data = json_decode($content, true);
            
            if ($data && isset($data['expires']) && $data['expires'] > time()) {
                return $data['value'];
            }
            
            // Expired cache
            unlink($cacheFile);
        }
        
        return $default;
    }

    /**
     * Set cache value
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     */
    private function setCacheValue(string $key, $value, int $ttl): void
    {
        $cacheDir = storage_path('cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = storage_path('cache/' . md5($key) . '.cache');
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($cacheFile, json_encode($data));
    }

    /**
     * Override securityErrorResponse to add security headers to error responses
     *
     * @param string $message
     * @param array $logData
     * @return JsonResponse
     */
    protected function securityErrorResponse(
        string $message = 'Security validation failed',
        array $logData = []
    ): JsonResponse {
        // Log the security incident
        Log::warning('Security error response generated', array_merge([
            'message' => $message,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ], $logData));

        // Create error response
        $response = $this->errorResponse(
            'Request validation failed',
            'The request could not be processed due to invalid data',
            400,
            [],
            'SECURITY_VALIDATION_FAILED'
        );

        // Apply security headers to the error response
        return $this->addSecurityHeaders($response);
    }
}