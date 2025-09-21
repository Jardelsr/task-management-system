<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Carbon\Carbon;

/**
 * SQL Injection Protection Service
 * 
 * Provides comprehensive protection against SQL injection attacks by:
 * - Sanitizing database inputs
 * - Validating query parameters
 * - Providing safe query builders
 * - Logging suspicious activities
 */
class SqlInjectionProtectionService
{
    /**
     * SQL injection patterns to detect
     */
    private const SQL_INJECTION_PATTERNS = [
        // Basic SQL keywords
        '/(\b(union|select|insert|update|delete|drop|create|alter|truncate|exec|execute|declare|sp_|xp_)\b)/i',
        
        // Comment patterns
        '/(\-\-|\#|\/\*|\*\/)/i',
        
        // Boolean-based injection patterns (improved)
        '/(\bor\b|\band\b)\s*\d+\s*=\s*\d+/i',
        '/(\bor\b|\band\b)\s*[\'"`][^\'"`]*[\'"`]\s*=\s*[\'"`][^\'"`]*[\'"`]/i',
        '/(\bor\b|\band\b)\s*\d+\s*(<|>|<=|>=|<>|!=)\s*\d+/i',
        '/(\bor\b|\band\b)\s*[\'"`][^\'"`]*[\'"`]\s*(<|>|<=|>=|<>|!=)\s*[\'"`][^\'"`]*[\'"`]/i',
        
        // Simple OR/AND conditions that are commonly used in SQLi
        '/[\'"`]\s*(or|and)\s*[\'"`][a-z]*[\'"`]\s*=\s*[\'"`][a-z]*[\'"`]/i',
        
        // Union-based injection
        '/union\s+(all\s+)?select/i',
        '/union\s+(all\s+)?select\s+null/i',
        
        // Time-based injection
        '/(sleep|benchmark|pg_sleep|waitfor)\s*\(/i',
        
        // Information schema attacks
        '/information_schema\.|sysobjects|syscolumns|sys\./i',
        
        // Hex/char encoding attempts
        '/(0x[0-9a-f]+|char\s*\(|ascii\s*\()/i',
        
        // Substring/concat functions often used in blind SQLi
        '/(substring|concat|mid|left|right)\s*\(/i',
        
        // Database version/user functions
        '/(version\s*\(|user\s*\(|database\s*\(|@@)/i',
        
        // Load file operations
        '/(load_file|into\s+outfile|into\s+dumpfile)/i',
        
        // Stacked queries
        '/;\s*(select|insert|update|delete|drop|create|alter)/i',
        
        // Additional patterns for edge cases
        '/\'\s*or\s*\'/i',  // 'OR' pattern
        '/\"\s*or\s*\"/i',  // "OR" pattern
    ];

    /**
     * Dangerous SQL functions that should never be in user input
     */
    private const DANGEROUS_FUNCTIONS = [
        'load_file', 'into outfile', 'into dumpfile', 'sp_executesql',
        'xp_cmdshell', 'sp_configure', 'openrowset', 'opendatasource',
        'bulk insert', 'bcp'
    ];

    /**
     * Sanitize input to prevent SQL injection
     *
     * @param mixed $input
     * @param string $context Additional context for logging
     * @return mixed
     */
    public function sanitizeInput($input, string $context = 'unknown')
    {
        if (is_null($input)) {
            return null;
        }

        if (is_array($input)) {
            return $this->sanitizeArray($input, $context);
        }

        if (!is_string($input)) {
            return $input;
        }

        // Log sanitization attempt
        $this->logSanitizationAttempt($input, $context);

        // Detect potential SQL injection
        if ($this->detectSqlInjection($input)) {
            $this->handleSqlInjectionAttempt($input, $context);
            return $this->sanitizeString($input);
        }

        return $input;
    }

    /**
     * Sanitize array inputs recursively
     *
     * @param array $array
     * @param string $context
     * @return array
     */
    private function sanitizeArray(array $array, string $context): array
    {
        $sanitized = [];
        foreach ($array as $key => $value) {
            $sanitizedKey = $this->sanitizeInput($key, $context . '.key');
            $sanitizedValue = $this->sanitizeInput($value, $context . '.' . $key);
            $sanitized[$sanitizedKey] = $sanitizedValue;
        }
        return $sanitized;
    }

    /**
     * Detect SQL injection patterns in input
     *
     * @param string $input
     * @return bool
     */
    public function detectSqlInjection(string $input): bool
    {
        $normalizedInput = strtolower(trim($input));

        // Quick check for obviously safe inputs
        if (strlen($normalizedInput) < 3) {
            return false;
        }
        
        // Skip detection for inputs that are clearly safe
        if (preg_match('/^[a-zA-Z0-9\s\-_@.,:!?()]+$/', $input) && 
            !preg_match('/(union|select|insert|update|delete|drop|or|and)/i', $normalizedInput)) {
            return false;
        }

        // Check against SQL injection patterns
        foreach (self::SQL_INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalizedInput)) {
                return true;
            }
        }

        // Check for dangerous functions
        foreach (self::DANGEROUS_FUNCTIONS as $function) {
            if (strpos($normalizedInput, strtolower($function)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize string input
     *
     * @param string $input
     * @return string
     */
    private function sanitizeString(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Only apply aggressive sanitization if SQL injection is detected
        if ($this->detectSqlInjection($input)) {
            // Remove SQL comments
            $input = preg_replace('/(\-\-|\#).*$/', '', $input);
            $input = preg_replace('/\/\*.*?\*\//', '', $input);
            
            // Remove dangerous SQL keywords (case-insensitive)
            foreach (self::DANGEROUS_FUNCTIONS as $function) {
                $input = preg_replace('/' . preg_quote($function, '/') . '/i', '', $input);
            }
            
            // Escape dangerous characters only if needed
            $input = addslashes($input);
        }
        
        return trim($input);
    }

    /**
     * Create safe query builder with input validation
     *
     * @param string $table
     * @param array $conditions
     * @return Builder
     */
    public function safeQuery(string $table, array $conditions = []): Builder
    {
        // Validate table name
        $this->validateTableName($table);

        // Start query builder
        $query = DB::table($table);

        // Add conditions safely
        foreach ($conditions as $field => $value) {
            $this->validateFieldName($field);
            
            if (is_array($value)) {
                $query->whereIn($field, $this->sanitizeArray($value, "query.{$table}.{$field}"));
            } else {
                $query->where($field, '=', $this->sanitizeInput($value, "query.{$table}.{$field}"));
            }
        }

        return $query;
    }

    /**
     * Execute safe parameterized query
     *
     * @param string $sql
     * @param array $bindings
     * @param string $connection
     * @return mixed
     */
    public function safeRawQuery(string $sql, array $bindings = [], string $connection = 'mysql')
    {
        // Validate the SQL query for dangerous patterns
        if ($this->detectSqlInjection($sql)) {
            throw new InvalidArgumentException('Potentially dangerous SQL query detected');
        }

        // Sanitize bindings
        $sanitizedBindings = [];
        foreach ($bindings as $key => $value) {
            $sanitizedBindings[$key] = $this->sanitizeInput($value, "raw_query.binding.{$key}");
        }

        // Log the query attempt
        try {
            Log::info('Safe raw query execution', [
                'sql' => $sql,
                'binding_count' => count($sanitizedBindings),
                'connection' => $connection
            ]);
        } catch (\Exception $e) {
            // Fallback logging if Log facade is not available
            error_log("Safe raw query execution: {$connection}");
        }

        return DB::connection($connection)->select($sql, $sanitizedBindings);
    }

    /**
     * Validate table name to prevent injection
     *
     * @param string $table
     * @throws InvalidArgumentException
     */
    public function validateTableName(string $table): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new InvalidArgumentException("Invalid table name: {$table}");
        }

        if ($this->detectSqlInjection($table)) {
            throw new InvalidArgumentException("Potentially malicious table name: {$table}");
        }
    }

    /**
     * Validate field name to prevent injection
     *
     * @param string $field
     * @throws InvalidArgumentException
     */
    public function validateFieldName(string $field): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.]*$/', $field)) {
            throw new InvalidArgumentException("Invalid field name: {$field}");
        }

        if ($this->detectSqlInjection($field)) {
            throw new InvalidArgumentException("Potentially malicious field name: {$field}");
        }
    }

    /**
     * Log sanitization attempt for security monitoring
     *
     * @param string $input
     * @param string $context
     */
    private function logSanitizationAttempt(string $input, string $context): void
    {
        if (strlen($input) > 1000 || $this->detectSqlInjection($input)) {
            $requestData = [];
            
            // Safely get request data if available
            try {
                if (function_exists('request') && request()) {
                    $requestData = [
                        'user_ip' => request()->ip() ?? 'unknown',
                        'user_agent' => request()->header('User-Agent') ?? 'unknown',
                    ];
                } else {
                    $requestData = [
                        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    ];
                }
            } catch (\Exception $e) {
                $requestData = [
                    'user_ip' => 'unavailable',
                    'user_agent' => 'unavailable',
                    'note' => 'Running in test environment'
                ];
            }
            
            try {
                Log::warning('SQL injection protection triggered', array_merge([
                    'context' => $context,
                    'input_length' => strlen($input),
                    'input_preview' => substr($input, 0, 100),
                    'suspicious_patterns' => $this->detectSqlInjection($input),
                    'timestamp' => Carbon::now()->toISOString()
                ], $requestData));
            } catch (\Exception $e) {
                // Fallback to error_log if Log facade is not available
                error_log("SQL injection protection: {$context} - " . substr($input, 0, 100));
            }
        }
    }

    /**
     * Handle SQL injection attempt
     *
     * @param string $input
     * @param string $context
     */
    private function handleSqlInjectionAttempt(string $input, string $context): void
    {
        $requestData = [];
        
        // Safely get request data if available
        try {
            if (function_exists('request') && request()) {
                $requestData = [
                    'user_ip' => request()->ip() ?? 'unknown',
                    'user_agent' => request()->header('User-Agent') ?? 'unknown',
                    'request_uri' => request()->getRequestUri() ?? 'unknown',
                    'request_method' => request()->method() ?? 'unknown',
                ];
            } else {
                $requestData = [
                    'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                ];
            }
        } catch (\Exception $e) {
            $requestData = [
                'user_ip' => 'test_environment',
                'user_agent' => 'test_environment',
                'request_uri' => 'test_environment',
                'request_method' => 'test_environment',
                'note' => 'Running in test mode'
            ];
        }
        
        try {
            Log::critical('SQL INJECTION ATTEMPT DETECTED', array_merge([
                'context' => $context,
                'malicious_input' => $input,
                'input_length' => strlen($input),
                'timestamp' => Carbon::now()->toISOString(),
                'severity' => 'CRITICAL'
            ], $requestData));
        } catch (\Exception $e) {
            // Fallback to error_log if Log facade is not available
            error_log("CRITICAL: SQL injection attempt - {$context} - " . substr($input, 0, 100));
        }

        // Could implement additional security measures here:
        // - Rate limiting
        // - IP blocking
        // - Alert notifications
    }

    /**
     * Validate and sanitize ORDER BY clause
     *
     * @param string $orderBy
     * @param array $allowedFields
     * @return string
     */
    public function sanitizeOrderBy(string $orderBy, array $allowedFields): string
    {
        // Extract field and direction
        $parts = explode(' ', trim($orderBy));
        $field = $parts[0];
        $direction = strtoupper($parts[1] ?? 'ASC');

        // Validate field name
        if (!in_array($field, $allowedFields)) {
            throw new InvalidArgumentException("Invalid order field: {$field}");
        }

        // Validate direction
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        return "{$field} {$direction}";
    }

    /**
     * Sanitize LIMIT clause
     *
     * @param mixed $limit
     * @param int $maxLimit
     * @return int
     */
    public function sanitizeLimit($limit, int $maxLimit = 1000): int
    {
        $limit = (int) $limit;
        
        if ($limit < 1) {
            return 10; // Default limit
        }
        
        if ($limit > $maxLimit) {
            return $maxLimit;
        }
        
        return $limit;
    }

    /**
     * Sanitize OFFSET clause
     *
     * @param mixed $offset
     * @return int
     */
    public function sanitizeOffset($offset): int
    {
        $offset = (int) $offset;
        return max(0, $offset);
    }

    /**
     * Create safe search conditions
     *
     * @param string $searchTerm
     * @param array $searchFields
     * @return array
     */
    public function createSafeSearchConditions(string $searchTerm, array $searchFields): array
    {
        $sanitizedTerm = $this->sanitizeInput($searchTerm, 'search_term');
        $conditions = [];

        foreach ($searchFields as $field) {
            $this->validateFieldName($field);
            $conditions[] = [$field, 'LIKE', "%{$sanitizedTerm}%"];
        }

        return $conditions;
    }
}