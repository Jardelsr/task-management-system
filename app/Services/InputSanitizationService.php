<?php

namespace App\Services;

use InvalidArgumentException;
use Illuminate\Support\Facades\Log;
use App\Services\SqlInjectionProtectionService;

/**
 * Comprehensive Input Sanitization Service
 * 
 * Provides various sanitization methods to clean and secure user input
 * while maintaining data integrity for legitimate use cases.
 */
class InputSanitizationService
{
    /**
     * Comprehensive sanitization patterns
     */
    private const XSS_PATTERNS = [
        // Script tags and events
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
        '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi',
        '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/mi',
        '/<applet\b[^<]*(?:(?!<\/applet>)<[^<]*)*<\/applet>/mi',
        '/<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/mi',
        '/<link\b[^<]*>/mi',
        '/<meta\b[^<]*>/mi',
        '/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi',
        
        // Event handlers
        '/\s*on\w+\s*=\s*["\'][^"\']*["\']?\s*/mi',
        '/\s*on\w+\s*=\s*[^"\'\s>]+/mi',
        
        // Javascript and vbscript URLs
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/data\s*:\s*text\/html/i',
        
        // Expression and eval patterns
        '/expression\s*\(/i',
        '/eval\s*\(/i',
        '/@import/i'
    ];

    private const SQL_PATTERNS = [
        // Basic SQL injection patterns
        '/(\b(union|select|insert|update|delete|drop|create|alter|exec|execute|declare|sp_|xp_)\b)/i',
        '/(\-\-|\#|\/\*|\*\/)/i',
        '/(\bor\b|\band\b)\s*\d+\s*=\s*\d+/i',
        '/(\bor\b|\band\b)\s*[\'"`][^\'"`]*[\'"`]\s*=\s*[\'"`][^\'"`]*[\'"`]/i',
        '/(\bor\b|\band\b)\s*\d+\s*(<|>|<=|>=|<>|!=)\s*\d+/i',
    ];

    private const COMMAND_INJECTION_PATTERNS = [
        '/(\||&&|;|`|\$\(|\${|<|>|>>)/i',
        '/\b(cat|ls|pwd|whoami|id|uname|ps|netstat|ifconfig|ping|wget|curl|nc|ncat|telnet|ssh|ftp|tftp|scp|rsync|tar|gzip|chmod|chown|su|sudo|passwd|crontab|service|systemctl|kill|killall|pkill|nohup|screen|tmux|history|env|printenv|set|unset|export|alias|which|whereis|locate|find|grep|awk|sed|sort|uniq|head|tail|wc|tr|cut|tee|xargs)\b/i'
    ];

    private const PATH_TRAVERSAL_PATTERNS = [
        '/\.{2,}\//',
        '/\.\.\\\/',
        '/\0/',
        '/\x00/',
        '/%2e%2e%2f/i',
        '/%2e%2e%5c/i',
        '/%c0%ae%c0%ae%c0%af/i',
        '/\.\./i'
    ];

    /**
     * Sanitize input based on its expected type
     *
     * @param mixed $value
     * @param string $type Expected data type (string, email, url, integer, float, boolean, json, html, filename, sql_safe)
     * @param array $options Additional sanitization options
     * @return mixed
     */
    public function sanitize($value, string $type = 'string', array $options = [])
    {
        if (is_null($value)) {
            return null;
        }

        // Log sanitization attempt for security monitoring
        Log::debug('Input sanitization', [
            'type' => $type,
            'original_length' => is_string($value) ? strlen($value) : 'non-string',
            'has_suspicious_patterns' => $this->hasSuspiciousPatterns($value)
        ]);

        switch (strtolower($type)) {
            case 'string':
            case 'text':
                return $this->sanitizeString($value, $options);
                
            case 'email':
                return $this->sanitizeEmail($value);
                
            case 'url':
                return $this->sanitizeUrl($value);
                
            case 'integer':
            case 'int':
                return $this->sanitizeInteger($value, $options);
                
            case 'float':
            case 'decimal':
            case 'number':
                return $this->sanitizeFloat($value, $options);
                
            case 'boolean':
            case 'bool':
                return $this->sanitizeBoolean($value);
                
            case 'json':
                return $this->sanitizeJson($value, $options);
                
            case 'html':
                return $this->sanitizeHtml($value, $options);
                
            case 'filename':
                return $this->sanitizeFilename($value);
                
            case 'sql_safe':
                return $this->sanitizeSqlSafe($value);
                
            case 'database_query':
                return $this->sanitizeDatabaseQuery($value, $options);
                
            case 'array':
                return $this->sanitizeArray($value, $options);
                
            default:
                throw new InvalidArgumentException("Unknown sanitization type: {$type}");
        }
    }

    /**
     * Sanitize string input with comprehensive XSS protection
     *
     * @param mixed $value
     * @param array $options
     * @return string
     */
    public function sanitizeString($value, array $options = []): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = (string) $value;
        
        // Remove null bytes
        $value = str_replace(["\0", "\x00"], '', $value);
        
        // Normalize line endings
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        
        // Remove or replace control characters (except allowed ones)
        $allowedControlChars = isset($options['allow_newlines']) && $options['allow_newlines'] ? ["\n", "\t"] : [];
        $value = preg_replace_callback('/[\x00-\x1F\x7F]/', function ($matches) use ($allowedControlChars) {
            return in_array($matches[0], $allowedControlChars) ? $matches[0] : '';
        }, $value);
        
        // Basic XSS protection
        if (!isset($options['skip_xss_protection']) || !$options['skip_xss_protection']) {
            $value = $this->removeXssPatterns($value);
        }
        
        // HTML encode if requested or by default
        if (!isset($options['skip_html_encode']) || !$options['skip_html_encode']) {
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        }
        
        // Trim whitespace
        if (!isset($options['skip_trim']) || !$options['skip_trim']) {
            $value = trim($value);
        }
        
        // Length limit
        if (isset($options['max_length'])) {
            $value = mb_substr($value, 0, $options['max_length'], 'UTF-8');
        }
        
        return $value;
    }

    /**
     * Sanitize email addresses
     *
     * @param mixed $value
     * @return string|null
     */
    public function sanitizeEmail($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = (string) $value;
        $value = trim(strtolower($value));
        
        // Remove dangerous characters
        $value = preg_replace('/[^a-zA-Z0-9@._+-]/', '', $value);
        
        // Basic email format validation
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        return $value;
    }

    /**
     * Sanitize URLs
     *
     * @param mixed $value
     * @return string|null
     */
    public function sanitizeUrl($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = (string) $value;
        $value = trim($value);
        
        // Remove dangerous protocols
        $dangerousProtocols = ['javascript:', 'vbscript:', 'data:', 'file:', 'ftp:'];
        foreach ($dangerousProtocols as $protocol) {
            if (stripos($value, $protocol) === 0) {
                return null;
            }
        }
        
        // Basic URL validation
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        return $value;
    }

    /**
     * Sanitize integer values
     *
     * @param mixed $value
     * @param array $options
     * @return int|null
     */
    public function sanitizeInteger($value, array $options = []): ?int
    {
        // Handle null or empty values
        if (is_null($value) || $value === '') {
            return null;
        }
        
        // Convert to integer
        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($intValue === false) {
            return null;
        }
        
        // Apply range limits
        if (isset($options['min']) && $intValue < $options['min']) {
            return null;
        }
        
        if (isset($options['max']) && $intValue > $options['max']) {
            return null;
        }
        
        return $intValue;
    }

    /**
     * Sanitize float values
     *
     * @param mixed $value
     * @param array $options
     * @return float|null
     */
    public function sanitizeFloat($value, array $options = []): ?float
    {
        // Handle null or empty values
        if (is_null($value) || $value === '') {
            return null;
        }
        
        // Convert to float
        $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT);
        
        if ($floatValue === false) {
            return null;
        }
        
        // Apply range limits
        if (isset($options['min']) && $floatValue < $options['min']) {
            return null;
        }
        
        if (isset($options['max']) && $floatValue > $options['max']) {
            return null;
        }
        
        // Apply precision limit
        if (isset($options['precision'])) {
            $floatValue = round($floatValue, $options['precision']);
        }
        
        return $floatValue;
    }

    /**
     * Sanitize boolean values
     *
     * @param mixed $value
     * @return bool|null
     */
    public function sanitizeBoolean($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_null($value) || $value === '') {
            return null;
        }
        
        // Handle string representations
        if (is_string($value)) {
            $value = trim(strtolower($value));
            
            if (in_array($value, ['true', '1', 'yes', 'on'])) {
                return true;
            }
            
            if (in_array($value, ['false', '0', 'no', 'off'])) {
                return false;
            }
        }
        
        // Handle numeric values
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        return null;
    }

    /**
     * Sanitize JSON input
     *
     * @param mixed $value
     * @param array $options
     * @return array|null
     */
    public function sanitizeJson($value, array $options = []): ?array
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value, $options);
        }
        
        if (!is_string($value)) {
            return null;
        }
        
        // Remove potential XSS from JSON string
        $value = $this->removeXssPatterns($value);
        
        // Parse JSON
        $decoded = json_decode($value, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        // Recursively sanitize the decoded array
        return $this->sanitizeArray($decoded, $options);
    }

    /**
     * Sanitize HTML content (very restrictive by default)
     *
     * @param mixed $value
     * @param array $options
     * @return string
     */
    public function sanitizeHtml($value, array $options = []): string
    {
        if (!is_string($value)) {
            return '';
        }
        
        // By default, strip all HTML tags
        $allowedTags = $options['allowed_tags'] ?? [];
        
        if (empty($allowedTags)) {
            return strip_tags($value);
        }
        
        // Allow specific tags only
        return strip_tags($value, implode('', $allowedTags));
    }

    /**
     * Sanitize filename
     *
     * @param mixed $value
     * @return string|null
     */
    public function sanitizeFilename($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        
        // Remove path traversal patterns
        foreach (self::PATH_TRAVERSAL_PATTERNS as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        // Remove dangerous characters
        $value = preg_replace('/[^a-zA-Z0-9._-]/', '_', $value);
        
        // Remove multiple consecutive underscores
        $value = preg_replace('/_+/', '_', $value);
        
        // Trim and ensure not empty
        $value = trim($value, '_.-');
        
        if (empty($value)) {
            return null;
        }
        
        return $value;
    }

    /**
     * Sanitize input to be SQL injection safe
     *
     * @param mixed $value
     * @return string|null
     */
    public function sanitizeSqlSafe($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        
        $value = (string) $value;
        
        // Check for SQL injection patterns
        foreach (self::SQL_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                Log::warning('Potential SQL injection attempt detected', [
                    'value' => substr($value, 0, 100),
                    'pattern' => $pattern
                ]);
                return null;
            }
        }
        
        // Escape SQL special characters
        $value = str_replace(["'", '"', '\\', "\x00", "\n", "\r", "\x1a"], ["''", '""', '\\\\', '\\0', '\\n', '\\r', '\\Z'], $value);
        
        return $value;
    }

    /**
     * Sanitize array recursively
     *
     * @param array $value
     * @param array $options
     * @return array
     */
    public function sanitizeArray(array $value, array $options = []): array
    {
        $sanitized = [];
        $maxDepth = $options['max_depth'] ?? 10;
        $currentDepth = $options['current_depth'] ?? 0;
        
        if ($currentDepth >= $maxDepth) {
            return $sanitized;
        }
        
        foreach ($value as $key => $item) {
            // Sanitize the key
            $cleanKey = $this->sanitizeString($key, ['skip_html_encode' => true]);
            
            // Sanitize the value
            if (is_array($item)) {
                $itemOptions = $options;
                $itemOptions['current_depth'] = $currentDepth + 1;
                $sanitized[$cleanKey] = $this->sanitizeArray($item, $itemOptions);
            } elseif (is_string($item)) {
                $sanitized[$cleanKey] = $this->sanitizeString($item, $options);
            } else {
                $sanitized[$cleanKey] = $item;
            }
        }
        
        return $sanitized;
    }

    /**
     * Remove XSS patterns from input
     *
     * @param string $value
     * @return string
     */
    private function removeXssPatterns(string $value): string
    {
        // Apply XSS patterns
        foreach (self::XSS_PATTERNS as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        // Check for command injection patterns
        foreach (self::COMMAND_INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                Log::warning('Potential command injection attempt detected', [
                    'value' => substr($value, 0, 100),
                    'pattern' => $pattern
                ]);
                $value = preg_replace($pattern, '', $value);
            }
        }
        
        return $value;
    }

    /**
     * Check if input contains suspicious patterns
     *
     * @param mixed $value
     * @return bool
     */
    public function hasSuspiciousPatterns($value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        
        $allPatterns = array_merge(
            self::XSS_PATTERNS,
            self::SQL_PATTERNS,
            self::COMMAND_INJECTION_PATTERNS,
            self::PATH_TRAVERSAL_PATTERNS
        );
        
        foreach ($allPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Comprehensive input sanitization for mixed data
     *
     * @param mixed $data
     * @param array $typeMap Field to type mapping
     * @param array $globalOptions Global sanitization options
     * @return mixed
     */
    public function sanitizeInput($data, array $typeMap = [], array $globalOptions = [])
    {
        if (is_array($data)) {
            $sanitized = [];
            
            foreach ($data as $key => $value) {
                $cleanKey = $this->sanitizeString($key, ['skip_html_encode' => true]);
                $type = $typeMap[$key] ?? $typeMap[$cleanKey] ?? 'string';
                $options = $globalOptions[$key] ?? $globalOptions[$cleanKey] ?? [];
                
                $sanitized[$cleanKey] = $this->sanitize($value, $type, $options);
            }
            
            return $sanitized;
        }
        
        return $this->sanitize($data, 'string', $globalOptions);
    }

    /**
     * Enhanced SQL injection protection for database queries
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    private function sanitizeDatabaseQuery($value, array $options = [])
    {
        if (is_null($value)) {
            return null;
        }

        // Import SQL injection protection service
        $sqlProtectionService = app(SqlInjectionProtectionService::class);
        
        return $sqlProtectionService->sanitizeInput($value, $options['context'] ?? 'database_query');
    }

    /**
     * Enhanced suspicious pattern detection with SQL injection focus
     *
     * @param mixed $value
     * @return bool
     */
    public function hasAdvancedSuspiciousPatterns($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Use SQL injection protection service for detection
        $sqlProtectionService = app(SqlInjectionProtectionService::class);
        
        return $sqlProtectionService->detectSqlInjection($value) || $this->hasSuspiciousPatterns($value);
    }
}