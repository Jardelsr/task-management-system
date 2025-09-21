<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when database connection fails
 */
class DatabaseConnectionException extends DatabaseException
{
    /**
     * Connection type that failed
     *
     * @var string
     */
    protected string $connectionType;

    /**
     * Connection configuration details (sanitized)
     *
     * @var array
     */
    protected array $connectionConfig;

    /**
     * Number of failed attempts
     *
     * @var int
     */
    protected int $attempts;

    /**
     * Create a new DatabaseConnectionException instance
     *
     * @param string $message
     * @param string $connectionType
     * @param array $connectionConfig
     * @param int $attempts
     * @param int $code
     */
    public function __construct(
        string $message = 'Database connection failed',
        string $connectionType = 'mysql',
        array $connectionConfig = [],
        int $attempts = 1,
        int $code = 503
    ) {
        $this->connectionType = $connectionType;
        $this->connectionConfig = $this->sanitizeConfig($connectionConfig);
        $this->attempts = $attempts;
        
        parent::__construct($message, 'database_connection', [
            'connection_type' => $connectionType,
            'attempts' => $attempts,
            'config' => $this->connectionConfig
        ], $code);
    }

    /**
     * Get the connection type that failed
     *
     * @return string
     */
    public function getConnectionType(): string
    {
        return $this->connectionType;
    }

    /**
     * Get the sanitized connection configuration
     *
     * @return array
     */
    public function getConnectionConfig(): array
    {
        return $this->connectionConfig;
    }

    /**
     * Get the number of failed attempts
     *
     * @return int
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Get the error details for API response
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return [
            'error' => 'Database connection failed',
            'message' => $this->getMessage(),
            'connection_type' => $this->connectionType,
            'attempts' => $this->attempts,
            'code' => 'DATABASE_CONNECTION_ERROR',
            'suggestion' => $this->getConnectionSuggestion()
        ];
    }

    /**
     * Get suggestion for fixing the connection issue
     *
     * @return string
     */
    public function getConnectionSuggestion(): string
    {
        switch ($this->connectionType) {
            case 'mysql':
                return 'Check if MySQL server is running and accessible. Verify database credentials and network connectivity.';
            case 'mongodb':
                return 'Check if MongoDB server is running and accessible. Verify MongoDB configuration and network connectivity.';
            default:
                return 'Check if the database server is running and accessible. Verify connection configuration.';
        }
    }

    /**
     * Sanitize configuration to remove sensitive information
     *
     * @param array $config
     * @return array
     */
    private function sanitizeConfig(array $config): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'key'];
        
        return array_filter($config, function($key) use ($sensitiveKeys) {
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains(strtolower($key), $sensitive)) {
                    return false;
                }
            }
            return true;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Check if this is a temporary connection issue
     *
     * @return bool
     */
    public function isTemporary(): bool
    {
        $temporaryMessages = [
            'connection timeout',
            'too many connections',
            'server has gone away',
            'connection refused',
            'network is unreachable'
        ];

        $message = strtolower($this->getMessage());
        
        foreach ($temporaryMessages as $tempMessage) {
            if (str_contains($message, $tempMessage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get recommended retry delay in seconds
     *
     * @return int
     */
    public function getRetryDelay(): int
    {
        if ($this->isTemporary()) {
            return min(30, $this->attempts * 5); // Max 30 seconds, increases with attempts
        }

        return 60; // Longer delay for non-temporary issues
    }
}