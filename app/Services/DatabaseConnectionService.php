<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\DatabaseException;
use PDO;
use PDOException;
use Exception;
use Throwable;
use Carbon\Carbon;

/**
 * Service to handle database connection management and health checks
 */
class DatabaseConnectionService
{
    /**
     * Maximum connection attempts
     */
    const MAX_CONNECTION_ATTEMPTS = 3;
    
    /**
     * Connection timeout in seconds
     */
    const CONNECTION_TIMEOUT = 30;
    
    /**
     * Retry delay in milliseconds
     */
    const RETRY_DELAY_MS = 1000;

    /**
     * Test database connection health
     *
     * @param string $connection Connection name (mysql, mongodb)
     * @return array Connection status and details
     */
    public static function testConnection(string $connection = 'mysql'): array
    {
        $startTime = microtime(true);
        
        try {
            switch ($connection) {
                case 'mysql':
                    return self::testMySQLConnection($startTime);
                case 'mongodb':
                    return self::testMongoDBConnection($startTime);
                default:
                    throw new DatabaseException("Unsupported database connection: {$connection}");
            }
        } catch (Throwable $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'failed',
                'connection' => $connection,
                'error' => $e->getMessage(),
                'response_time_ms' => $responseTime,
                'timestamp' => Carbon::now()->toISOString()
            ];
        }
    }

    /**
     * Test MySQL connection
     *
     * @param float $startTime
     * @return array
     * @throws DatabaseException
     */
    private static function testMySQLConnection(float $startTime): array
    {
        try {
            // Test basic PDO connection
            $pdo = DB::connection('mysql')->getPdo();
            
            if (!$pdo instanceof PDO) {
                throw new DatabaseException('Failed to establish PDO connection');
            }

            // Test database query
            $result = DB::connection('mysql')->select('SELECT 1 as test, DATABASE() as db, VERSION() as version');
            
            if (empty($result)) {
                throw new DatabaseException('Database query returned empty result');
            }

            $dbInfo = $result[0];
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'connection' => 'mysql',
                'database' => $dbInfo->db ?? 'unknown',
                'version' => $dbInfo->version ?? 'unknown',
                'response_time_ms' => $responseTime,
                'timestamp' => Carbon::now()->toISOString()
            ];

        } catch (PDOException $e) {
            throw new DatabaseException(
                'MySQL PDO connection failed: ' . $e->getMessage(),
                'connection_test',
                ['pdo_error_code' => $e->getCode()],
                500
            );
        } catch (Exception $e) {
            throw new DatabaseException(
                'MySQL connection test failed: ' . $e->getMessage(),
                'connection_test',
                [],
                500
            );
        }
    }

    /**
     * Test MongoDB connection
     *
     * @param float $startTime
     * @return array
     * @throws DatabaseException
     */
    private static function testMongoDBConnection(float $startTime): array
    {
        try {
            // Check if MongoDB extension is loaded
            if (!extension_loaded('mongodb')) {
                throw new DatabaseException('MongoDB extension not loaded');
            }

            $host = env('MONGO_HOST', 'localhost');
            $port = env('MONGO_PORT', 27017);
            $database = env('MONGO_DATABASE', 'task_logs');

            $connectionString = "mongodb://{$host}:{$port}";
            $manager = new \MongoDB\Driver\Manager($connectionString);

            // Test connection with ping
            $command = new \MongoDB\Driver\Command(['ping' => 1]);
            $result = $manager->executeCommand('admin', $command);
            
            if (!$result) {
                throw new DatabaseException('MongoDB ping failed');
            }

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'connection' => 'mongodb',
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'response_time_ms' => $responseTime,
                'timestamp' => Carbon::now()->toISOString()
            ];

        } catch (\MongoDB\Driver\Exception\ConnectionException $e) {
            throw new DatabaseException(
                'MongoDB connection failed: ' . $e->getMessage(),
                'connection_test',
                ['mongodb_error' => $e->getCode()],
                500
            );
        } catch (Exception $e) {
            throw new DatabaseException(
                'MongoDB connection test failed: ' . $e->getMessage(),
                'connection_test',
                [],
                500
            );
        }
    }

    /**
     * Execute database operation with connection retry
     *
     * @param callable $operation The database operation to execute
     * @param string $operationName Name for logging purposes
     * @param string $connection Connection name to use
     * @return mixed Operation result
     * @throws DatabaseException
     */
    public static function executeWithRetry(callable $operation, string $operationName, string $connection = 'mysql')
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_CONNECTION_ATTEMPTS) {
            try {
                // Test connection before executing operation
                $connectionStatus = self::testConnection($connection);
                
                if ($connectionStatus['status'] !== 'healthy') {
                    throw new DatabaseException(
                        "Database connection is not healthy: " . ($connectionStatus['error'] ?? 'Unknown error'),
                        $operationName,
                        $connectionStatus
                    );
                }

                // Execute the operation
                return $operation();

            } catch (PDOException $e) {
                $lastException = $e;
                $attempt++;

                // Check if error is connection-related
                if (self::isConnectionError($e)) {
                    Log::warning("Database connection error on attempt {$attempt}/" . self::MAX_CONNECTION_ATTEMPTS, [
                        'operation' => $operationName,
                        'connection' => $connection,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode()
                    ]);

                    if ($attempt < self::MAX_CONNECTION_ATTEMPTS) {
                        usleep(self::RETRY_DELAY_MS * 1000 * $attempt); // Exponential backoff
                        continue;
                    }
                } else {
                    // Non-connection errors should not be retried
                    break;
                }

            } catch (DatabaseException $e) {
                $lastException = $e;
                $attempt++;

                Log::warning("Database exception on attempt {$attempt}/" . self::MAX_CONNECTION_ATTEMPTS, [
                    'operation' => $operationName,
                    'connection' => $connection,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < self::MAX_CONNECTION_ATTEMPTS) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
                    continue;
                }
                break;

            } catch (Exception $e) {
                // Non-database exceptions are not retryable
                throw new DatabaseException(
                    "Operation '{$operationName}' failed: " . $e->getMessage(),
                    $operationName,
                    ['error_type' => get_class($e)],
                    500
                );
            }
        }

        // All attempts failed
        throw new DatabaseException(
            "Database operation '{$operationName}' failed after " . self::MAX_CONNECTION_ATTEMPTS . " attempts",
            $operationName,
            [
                'attempts' => self::MAX_CONNECTION_ATTEMPTS,
                'last_error' => $lastException ? $lastException->getMessage() : 'Unknown error',
                'error_code' => $lastException ? $lastException->getCode() : 0
            ],
            500
        );
    }

    /**
     * Check if exception is connection-related
     *
     * @param Throwable $e
     * @return bool
     */
    private static function isConnectionError(Throwable $e): bool
    {
        $connectionErrorCodes = [
            2002, // Connection refused
            2003, // Can't connect to MySQL server
            2006, // MySQL server has gone away
            2013, // Lost connection to MySQL server during query
            1040, // Too many connections
            1129, // Host is blocked because of too many connection errors
            1203, // User already has more than 'max_user_connections' active connections
        ];

        $connectionErrorMessages = [
            'connection refused',
            'server has gone away',
            'lost connection',
            'too many connections',
            'connection timeout',
            'can\'t connect to',
            'connection closed',
            'broken pipe'
        ];

        // Check error codes
        if (in_array($e->getCode(), $connectionErrorCodes)) {
            return true;
        }

        // Check error messages
        $message = strtolower($e->getMessage());
        foreach ($connectionErrorMessages as $errorMessage) {
            if (str_contains($message, $errorMessage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get connection health summary for all configured connections
     *
     * @return array
     */
    public static function getHealthSummary(): array
    {
        $connections = ['mysql'];
        
        // Add MongoDB if configured
        if (env('MONGO_HOST')) {
            $connections[] = 'mongodb';
        }

        $results = [];
        $allHealthy = true;

        foreach ($connections as $connection) {
            $result = self::testConnection($connection);
            $results[$connection] = $result;
            
            if ($result['status'] !== 'healthy') {
                $allHealthy = false;
            }
        }

        return [
            'overall_status' => $allHealthy ? 'healthy' : 'degraded',
            'connections' => $results,
            'timestamp' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Validate database configuration
     *
     * @param string $connection
     * @return array
     */
    public static function validateConfiguration(string $connection = 'mysql'): array
    {
        $config = config("database.connections.{$connection}");
        
        if (!$config) {
            return [
                'valid' => false,
                'errors' => ["Configuration for connection '{$connection}' not found"]
            ];
        }

        $errors = [];

        switch ($connection) {
            case 'mysql':
                if (empty($config['host'])) $errors[] = 'MySQL host not configured';
                if (empty($config['database'])) $errors[] = 'MySQL database not configured';
                if (empty($config['username'])) $errors[] = 'MySQL username not configured';
                break;

            case 'mongodb':
                if (empty($config['host'])) $errors[] = 'MongoDB host not configured';
                if (empty($config['database'])) $errors[] = 'MongoDB database not configured';
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'config' => array_filter($config, function($key) {
                return !in_array($key, ['password', 'username']); // Don't expose sensitive data
            }, ARRAY_FILTER_USE_KEY)
        ];
    }
}
