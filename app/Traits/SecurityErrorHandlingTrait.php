<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Exceptions\TaskOperationException;

/**
 * Trait for comprehensive security error handling
 */
trait SecurityErrorHandlingTrait
{
    /**
     * Suspicious patterns that might indicate attacks
     */
    private const SUSPICIOUS_PATTERNS = [
        // SQL Injection patterns
        'union\s+select',
        'drop\s+table',
        'delete\s+from',
        'insert\s+into',
        'update\s+set',
        'exec\s*\(',
        'sp_executesql',
        
        // XSS patterns
        '<script[^>]*>',
        'javascript:',
        'vbscript:',
        'onload\s*=',
        'onerror\s*=',
        'onclick\s*=',
        
        // Path traversal - fixed regex patterns  
        '\.\.\/\.\.',
        '\.\.\\\\\.\.\\\\',
        '%2e%2e%2f',
        '%2e%2e%5c',
        
        // Command injection
        ';\s*(rm|del|format)',
        '`[^`]*`',
        '\$\([^)]*\)',
        
        // LDAP injection - fixed regex patterns
        '\*\)\(',
        '\|\|',
        '&&',
        
        // XML injection
        '<!ENTITY',
        '<!DOCTYPE',
        '&[a-zA-Z]+;'
    ];

    /**
     * Validate request for security threats
     *
     * @param array $data Request data
     * @param array $additionalPatterns Custom patterns to check
     * @throws TaskOperationException
     */
    protected function validateRequestSecurity(array $data, array $additionalPatterns = []): void
    {
        $allPatterns = array_merge(self::SUSPICIOUS_PATTERNS, $additionalPatterns);
        $suspiciousFields = [];
        
        $this->scanArrayForThreats($data, $allPatterns, '', $suspiciousFields);
        
        if (!empty($suspiciousFields)) {
            $this->handleSecurityThreat('Suspicious patterns detected in request', $suspiciousFields);
        }
    }

    /**
     * Recursively scan array for security threats
     *
     * @param array $data
     * @param array $patterns
     * @param string $keyPath
     * @param array &$suspiciousFields
     */
    private function scanArrayForThreats(
        array $data, 
        array $patterns, 
        string $keyPath, 
        array &$suspiciousFields
    ): void {
        foreach ($data as $key => $value) {
            $currentPath = $keyPath ? "{$keyPath}.{$key}" : $key;
            
            if (is_array($value)) {
                $this->scanArrayForThreats($value, $patterns, $currentPath, $suspiciousFields);
            } elseif (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match("/{$pattern}/i", $value)) {
                        $suspiciousFields[] = [
                            'field' => $currentPath,
                            'pattern_matched' => $pattern,
                            'value_preview' => substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '')
                        ];
                    }
                }
            }
        }
    }

    /**
     * Handle detected security threat
     *
     * @param string $message
     * @param array $details
     * @throws TaskOperationException
     */
    private function handleSecurityThreat(string $message, array $details): void
    {
        // Log security incident
        Log::warning('Security threat detected', [
            'message' => $message,
            'details' => $details,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'timestamp' => now()->toISOString()
        ]);

        // Throw exception with minimal details for security
        throw new TaskOperationException(
            'Request contains invalid data',
            'security_validation',
            null,
            ['blocked_fields' => count($details)]
        );
    }

    /**
     * Validate file upload security
     *
     * @param string $filename
     * @param string $content
     * @param array $allowedExtensions
     * @param int $maxSize Maximum size in bytes
     * @throws TaskOperationException
     */
    protected function validateFileUploadSecurity(
        string $filename, 
        string $content, 
        array $allowedExtensions = ['txt', 'pdf', 'doc', 'docx'],
        int $maxSize = 5242880 // 5MB
    ): void {
        // Check file size
        if (strlen($content) > $maxSize) {
            throw new TaskOperationException(
                'File size exceeds maximum allowed size',
                'file_upload_security',
                null,
                ['max_size_mb' => round($maxSize / 1024 / 1024, 2)]
            );
        }

        // Check file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            throw new TaskOperationException(
                'File type not allowed',
                'file_upload_security',
                null,
                ['allowed_extensions' => $allowedExtensions]
            );
        }

        // Check for suspicious filename patterns
        $suspiciousFilenamePatterns = [
            '\.php$',
            '\.js$',
            '\.html?$',
            '\.asp$',
            '\.jsp$',
            '\.exe$',
            '\.bat$',
            '\.cmd$',
            '\.sh$',
            '\.\.',
            '\\x00'
        ];

        foreach ($suspiciousFilenamePatterns as $pattern) {
            if (preg_match("/{$pattern}/i", $filename)) {
                $this->handleSecurityThreat(
                    'Suspicious filename pattern detected',
                    [['filename' => $filename, 'pattern' => $pattern]]
                );
            }
        }

        // Check for suspicious file content
        $suspiciousContentPatterns = [
            '<?php',
            '<%',
            '<script',
            'eval\s*\(',
            'exec\s*\(',
            'system\s*\(',
            'shell_exec',
            'passthru',
            'file_get_contents',
            'file_put_contents',
            'fopen',
            'fwrite'
        ];

        foreach ($suspiciousContentPatterns as $pattern) {
            if (preg_match("/{$pattern}/i", $content)) {
                $this->handleSecurityThreat(
                    'Suspicious file content detected',
                    [['filename' => $filename, 'pattern' => $pattern]]
                );
            }
        }
    }

    /**
     * Enhanced input sanitization using InputSanitizationService
     *
     * @param mixed $data
     * @param array $typeMap Field to type mapping (optional)
     * @param array $options Sanitization options (optional)
     * @return mixed
     */
    protected function sanitizeInput($data, array $typeMap = [], array $options = [])
    {
        // Get the InputSanitizationService instance
        $sanitizationService = app(\App\Services\InputSanitizationService::class);
        
        return $sanitizationService->sanitizeInput($data, $typeMap, $options);
    }

    /**
     * Legacy sanitize method for backward compatibility
     * 
     * @deprecated Use sanitizeInput() instead
     * @param mixed $data
     * @return mixed
     */
    protected function legacySanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'legacySanitizeInput'], $data);
        }

        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            
            // HTML encode special characters
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            
            // Remove potentially dangerous tags
            $data = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $data);
            $data = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', '', $data);
            $data = preg_replace('/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi', '', $data);
            $data = preg_replace('/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/mi', '', $data);
            
            // Remove javascript: and vbscript: URLs
            $data = preg_replace('/javascript\s*:/i', '', $data);
            $data = preg_replace('/vbscript\s*:/i', '', $data);
            
            return trim($data);
        }

        return $data;
    }

    /**
     * Rate limiting check (simple implementation)
     *
     * @param string $key
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @throws TaskOperationException
     */
    protected function checkRateLimit(
        string $key, 
        int $maxAttempts = 60, 
        int $windowSeconds = 60
    ): void {
        $cacheKey = "rate_limit:{$key}:" . floor(time() / $windowSeconds);
        $attempts = $this->getCacheValue($cacheKey, 0);

        if ($attempts >= $maxAttempts) {
            $this->handleSecurityThreat(
                'Rate limit exceeded',
                [
                    'key' => $key,
                    'attempts' => $attempts,
                    'max_attempts' => $maxAttempts,
                    'window_seconds' => $windowSeconds
                ]
            );
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
     * Validate IP address against whitelist/blacklist
     *
     * @param array $whitelist
     * @param array $blacklist
     * @throws TaskOperationException
     */
    protected function validateIpAddress(
        array $whitelist = [], 
        array $blacklist = []
    ): void {
        $clientIp = request()->ip();

        // Check blacklist first
        if (!empty($blacklist) && in_array($clientIp, $blacklist)) {
            $this->handleSecurityThreat(
                'IP address is blacklisted',
                ['ip' => $clientIp]
            );
        }

        // Check whitelist if provided
        if (!empty($whitelist) && !in_array($clientIp, $whitelist)) {
            $this->handleSecurityThreat(
                'IP address not in whitelist',
                ['ip' => $clientIp]
            );
        }
    }

    /**
     * Create security error response
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

        // Return generic error to avoid information disclosure
        return $this->errorResponse(
            'Request validation failed',
            'The request could not be processed due to invalid data',
            400,
            [],
            'SECURITY_VALIDATION_FAILED'
        );
    }
}