<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DatabaseConnectionService;
use App\Http\Responses\ErrorResponseFormatter;
use App\Services\ErrorLoggingService;
use Carbon\Carbon;
use Throwable;

class HealthController extends Controller
{
    /**
     * Get application health status including database connections
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getHealth(Request $request): JsonResponse
    {
        try {
            $healthSummary = DatabaseConnectionService::getHealthSummary();
            $overallStatus = $healthSummary['overall_status'];
            
            // Log health check request
            ErrorLoggingService::logApiOperation($request, null, 'info', [
                'context' => 'health_check',
                'overall_status' => $overallStatus,
                'connections_count' => count($healthSummary['connections'])
            ]);

            $response = [
                'success' => true,
                'timestamp' => Carbon::now()->toISOString(),
                'service' => 'Task Management System',
                'status' => $overallStatus,
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
                'database' => $healthSummary['connections'],
                'uptime' => $this->getUptime(),
                'memory_usage' => $this->getMemoryUsage(),
            ];

            // Return appropriate status code based on health
            $statusCode = ($overallStatus === 'healthy') ? 200 : 503;

            return response()->json($response, $statusCode)
                ->withHeaders([
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'X-Health-Status' => $overallStatus,
                    'X-Service' => 'Task Management System'
                ]);

        } catch (Throwable $e) {
            ErrorLoggingService::logError($e, $request, [
                'context' => 'health_check_failure',
                'endpoint' => 'health'
            ]);

            return ErrorResponseFormatter::format(
                'Health Check Error',
                'Failed to retrieve system health status: ' . $e->getMessage(),
                500,
                'HEALTH_CHECK_ERROR'
            );
        }
    }

    /**
     * Test specific database connection
     *
     * @param Request $request
     * @param string $connection
     * @return JsonResponse
     */
    public function testConnection(Request $request, string $connection): JsonResponse
    {
        try {
            // Validate connection type
            if (!in_array($connection, ['mysql', 'mongodb'])) {
                return ErrorResponseFormatter::format(
                    'Invalid Connection',
                    "Unsupported connection type: {$connection}",
                    400,
                    'INVALID_CONNECTION_TYPE',
                    ['supported_connections' => ['mysql', 'mongodb']]
                );
            }

            $result = DatabaseConnectionService::testConnection($connection);
            
            ErrorLoggingService::logApiOperation($request, null, 'info', [
                'context' => 'connection_test',
                'connection' => $connection,
                'status' => $result['status']
            ]);

            $response = [
                'success' => $result['status'] === 'healthy',
                'timestamp' => Carbon::now()->toISOString(),
                'connection' => $result,
                'test_details' => [
                    'connection_type' => $connection,
                    'test_performed_at' => $result['timestamp'],
                    'response_time_ms' => $result['response_time_ms']
                ]
            ];

            $statusCode = ($result['status'] === 'healthy') ? 200 : 503;

            return response()->json($response, $statusCode)
                ->withHeaders([
                    'X-Connection-Status' => $result['status'],
                    'X-Connection-Type' => $connection,
                    'X-Response-Time-Ms' => $result['response_time_ms']
                ]);

        } catch (Throwable $e) {
            ErrorLoggingService::logError($e, $request, [
                'context' => 'connection_test_failure',
                'connection' => $connection
            ]);

            return ErrorResponseFormatter::databaseConnectionError(
                $connection,
                $e->getMessage(),
                1,
                false,
                30
            );
        }
    }

    /**
     * Get detailed database configuration validation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDatabaseConfig(Request $request): JsonResponse
    {
        try {
            $connections = ['mysql'];
            
            // Add MongoDB if configured
            if (env('MONGO_HOST')) {
                $connections[] = 'mongodb';
            }

            $configs = [];
            foreach ($connections as $connection) {
                $configs[$connection] = DatabaseConnectionService::validateConfiguration($connection);
            }

            ErrorLoggingService::logApiOperation($request, null, 'info', [
                'context' => 'database_config_check',
                'connections_checked' => count($configs)
            ]);

            return response()->json([
                'success' => true,
                'timestamp' => Carbon::now()->toISOString(),
                'database_configurations' => $configs,
                'environment' => config('app.env'),
                'debug_mode' => config('app.debug')
            ]);

        } catch (Throwable $e) {
            ErrorLoggingService::logError($e, $request, [
                'context' => 'database_config_error'
            ]);

            return ErrorResponseFormatter::format(
                'Configuration Error',
                'Failed to retrieve database configuration: ' . $e->getMessage(),
                500,
                'CONFIG_ERROR'
            );
        }
    }

    /**
     * Get application uptime
     *
     * @return array
     */
    private function getUptime(): array
    {
        // This is a simplified uptime calculation
        // In production, you might want to track this more precisely
        $startTime = filectime(base_path('bootstrap/app.php'));
        $uptime = time() - $startTime;

        return [
            'seconds' => $uptime,
            'human_readable' => $this->secondsToHuman($uptime),
            'started_at' => Carbon::createFromTimestamp($startTime)->toISOString()
        ];
    }

    /**
     * Get memory usage information
     *
     * @return array
     */
    private function getMemoryUsage(): array
    {
        return [
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Convert seconds to human readable format
     *
     * @param int $seconds
     * @return string
     */
    private function secondsToHuman(int $seconds): string
    {
        $units = [
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1
        ];

        $result = [];
        foreach ($units as $unit => $value) {
            $count = intval($seconds / $value);
            if ($count > 0) {
                $result[] = $count . ' ' . ($count > 1 ? $unit . 's' : $unit);
                $seconds %= $value;
            }
        }

        return empty($result) ? '0 seconds' : implode(', ', $result);
    }
}
