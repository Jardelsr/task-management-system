<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Rate Limiting Middleware for Lumen
 * 
 * Provides configurable rate limiting functionality to protect against
 * abuse and ensure fair resource usage across all clients.
 * 
 * Features:
 * - IP-based rate limiting
 * - Configurable limits per endpoint
 * - Sliding window approach
 * - Proper HTTP headers for client awareness
 * - Detailed logging for monitoring
 */
class RateLimitingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $profile Rate limiting profile to use
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $profile = 'default')
    {
        try {
            $key = $this->resolveRequestSignature($request);
            $config = $this->getRateLimitConfig($profile);
            
            $requests = $config['requests'];
            $perMinutes = $config['per_minutes'];
            $cacheKey = "rate_limit:{$key}";
            
            // Get current request count
            $currentRequests = Cache::get($cacheKey, 0);
            
            // Check if limit exceeded
            if ($currentRequests >= $requests) {
                return $this->buildRateLimitResponse($requests, $perMinutes, $currentRequests);
            }
            
            // Increment request counter
            $newCount = $currentRequests + 1;
            $ttl = $perMinutes * 60; // Convert minutes to seconds
            Cache::put($cacheKey, $newCount, $ttl);
            
            // Process the request
            $response = $next($request);
            
            // Add rate limiting headers to response
            return $this->addRateLimitHeaders($response, $requests, $newCount, $ttl);
            
        } catch (\Exception $e) {
            // Log the error for debugging
            if (function_exists('app') && app()->bound('log')) {
                app('log')->error('Rate limiting middleware error: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // In case of error, allow the request to continue
            return $next($request);
        }
    }
    
    /**
     * Create a unique signature for the request
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $ip = $this->getClientIp($request);
        $route = $request->getPathInfo();
        
        // Include method for different limits on different HTTP methods
        $method = $request->getMethod();
        
        return hash('sha256', $ip . '|' . $route . '|' . $method);
    }
    
    /**
     * Get the real client IP address
     */
    protected function getClientIp(Request $request): string
    {
        // Check for IP behind proxy/load balancer
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP format
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to request IP
        return $request->ip() ?: '0.0.0.0';
    }
    
    /**
     * Get rate limiting configuration for the given profile
     */
    protected function getRateLimitConfig(string $profile): array
    {
        $config = config('cache.rate_limiting', []);
        
        if (!isset($config[$profile])) {
            // Log warning about missing profile
            if (function_exists('app') && app()->bound('log')) {
                app('log')->warning("Rate limiting profile '{$profile}' not found, using default");
            }
            $profile = 'default';
        }
        
        return $config[$profile] ?? [
            'requests' => 60,
            'per_minutes' => 1
        ];
    }
    
    /**
     * Build response for rate limit exceeded
     */
    protected function buildRateLimitResponse(int $maxRequests, int $perMinutes, int $currentRequests): Response
    {
        $retryAfter = $perMinutes * 60; // Convert to seconds
        
        $responseData = [
            'error' => 'Rate limit exceeded',
            'message' => "Too many requests. Limit is {$maxRequests} requests per {$perMinutes} minute(s).",
            'details' => [
                'limit' => $maxRequests,
                'window_minutes' => $perMinutes,
                'current_requests' => $currentRequests,
                'retry_after_seconds' => $retryAfter
            ],
            'code' => 'RATE_LIMIT_EXCEEDED'
        ];
        
        $headers = [
            'X-RateLimit-Limit' => (string)$maxRequests,
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string)(time() + $retryAfter),
            'Retry-After' => (string)$retryAfter,
            'Content-Type' => 'application/json'
        ];
        
        // Create response using Laravel's response factory
        $response = new Response(
            json_encode($responseData), 
            429, 
            $headers
        );
        
        return $response;
    }
    
    /**
     * Add rate limiting headers to successful response
     */
    protected function addRateLimitHeaders($response, int $maxRequests, int $currentRequests, int $ttl)
    {
        $remaining = max(0, $maxRequests - $currentRequests);
        $resetTime = time() + $ttl;
        
        if (method_exists($response, 'withHeaders')) {
            return $response->withHeaders([
                'X-RateLimit-Limit' => $maxRequests,
                'X-RateLimit-Remaining' => $remaining,
                'X-RateLimit-Reset' => $resetTime
            ]);
        }
        
        // For responses that don't support withHeaders
        if (method_exists($response, 'header')) {
            $response->header('X-RateLimit-Limit', $maxRequests);
            $response->header('X-RateLimit-Remaining', $remaining);
            $response->header('X-RateLimit-Reset', $resetTime);
        }
        
        return $response;
    }
}