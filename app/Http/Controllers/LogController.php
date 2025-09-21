<?php

namespace App\Http\Controllers;

use App\Services\LogServiceInterface;
use App\Exceptions\LoggingException;
use App\Exceptions\DatabaseException;
use App\Http\Requests\ValidationHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
    /**
     * Log service instance
     *
     * @var LogServiceInterface
     */
    protected LogServiceInterface $logService;

    /**
     * LogController constructor with dependency injection
     *
     * @param LogServiceInterface $logService
     */
    public function __construct(LogServiceInterface $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Display a listing of recent logs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // If requesting specific log by ID
            if ($request->has('id')) {
                $log = $this->logService->findLogById($request->get('id'));
                
                if (!$log) {
                    throw new LoggingException('Log not found', 'find_by_id', ['id' => $request->get('id')]);
                }
                
                return $this->enhancedSuccessResponse(
                    $log,
                    'Log retrieved successfully',
                    200,
                    ['log_id' => $request->get('id')],
                    $request
                );
            }
            
            // Get logs with filters and pagination using the service
            $result = $this->logService->getLogsWithFilters($request);
            
            return $this->paginatedResponse(
                $result['logs']->toArray(),
                $result['pagination'],
                'Logs retrieved successfully'
            )->withHeaders([
                'X-Total-Count' => $result['pagination']['total'],
                'X-Page' => $result['pagination']['current_page'],
                'X-Per-Page' => $result['pagination']['per_page'],
                'X-API-Version' => config('api.version', '1.0')
            ]);
            
        } catch (LoggingException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@index', [
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);

            throw new LoggingException(
                'Failed to retrieve logs: ' . $e->getMessage(),
                'index'
            );
        }
    }

    /**
     * Display logs for a specific task
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function taskLogs(Request $request, int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            $limit = min(max((int) $request->query('limit', 50), 1), 1000);

            // Get logs for specific task
            $logs = $this->logService->getTaskLogs($validatedId, $limit);
            
            $pagination = [
                'current_page' => 1,
                'per_page' => $limit,
                'total' => $logs->count(),
                'total_pages' => 1,
                'has_next_page' => false,
                'has_previous_page' => false,
            ];

            return $this->paginatedResponse(
                $logs->toArray(),
                $pagination,
                "Logs for task {$validatedId} retrieved successfully"
            )->withHeaders([
                'X-Task-ID' => $validatedId,
                'X-Total-Count' => $logs->count(),
                'X-API-Version' => config('api.version', '1.0')
            ]);

        } catch (\App\Exceptions\TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@taskLogs', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve task logs: ' . $e->getMessage(),
                'task_logs',
                ['task_id' => $id]
            );
        }
    }

    /**
     * Get log statistics
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->logService->getLogStatistics();

            return $this->statsResponse($stats, 'Log statistics retrieved successfully');

        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@stats', [
                'error' => $e->getMessage()
            ]);

            throw new LoggingException(
                'Failed to retrieve log statistics: ' . $e->getMessage(),
                'stats'
            );
        }
    }

    /**
     * Handle root level logs endpoint: GET /logs?id=:id
     * Returns last 30 logs or specific log if id parameter is provided
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function rootLogs(Request $request): JsonResponse
    {
        try {
            $id = $request->query('id');

            // If id parameter is provided, return specific log
            if ($id) {
                $log = $this->logService->findLogById($id);
                
                if (!$log) {
                    throw new LoggingException(
                        'Log not found',
                        'find_by_id',
                        ['log_id' => $id]
                    );
                }

                return $this->successResponse(
                    $log,
                    'Log retrieved successfully',
                    200,
                    ['log_id' => $id]
                );
            }

            // If no id parameter, return last 30 logs
            $logs = $this->logService->getRecentLogs(30);

            return $this->logResponse(
                $logs,
                'Last 30 application logs retrieved successfully',
                ['count' => $logs->count(), 'limit' => 30]
            );

        } catch (LoggingException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@rootLogs', [
                'error' => $e->getMessage(),
                'request_id' => $request->query('id')
            ]);

            throw new LoggingException(
                'Failed to retrieve logs: ' . $e->getMessage(),
                'root_logs'
            );
        }
    }
}