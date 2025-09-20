<?php

namespace App\Http\Controllers;

use App\Repositories\LogRepositoryInterface;
use App\Exceptions\LoggingException;
use App\Exceptions\DatabaseException;
use App\Http\Requests\ValidationHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
    /**
     * Log repository instance
     *
     * @var LogRepositoryInterface
     */
    protected LogRepositoryInterface $logRepository;

    /**
     * LogController constructor with dependency injection
     *
     * @param LogRepositoryInterface $logRepository
     */
    public function __construct(LogRepositoryInterface $logRepository)
    {
        $this->logRepository = $logRepository;
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
            // Sanitize and validate parameters
            $sanitizedData = ValidationHelper::sanitizeInput($request->all());
            $request->replace($sanitizedData);
            
            $validatedParams = ValidationHelper::validateLogParameters($request);
            $limit = $validatedParams['limit'] ?? 50;

            $logs = $this->logRepository->findRecent($limit);

            return $this->logResponse(
                $logs,
                'Recent logs retrieved successfully',
                ['limit' => $limit, 'count' => $logs->count()]
            );

        } catch (\Exception $e) {
            \Log::error('Unexpected error in LogController@index', [
                'error' => $e->getMessage()
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
            $limit = (int) $request->query('limit', 50);
            $limit = min(max($limit, 1), 1000); // Ensure limit is between 1 and 1000

            $logs = $this->logRepository->findByTask($id, $limit);

            return $this->logResponse(
                $logs,
                "Logs for task {$id} retrieved successfully",
                [
                    'task_id' => $id,
                    'limit' => $limit,
                    'count' => $logs->count()
                ]
            );

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
            $stats = [
                'total_logs' => $this->logRepository->countByAction(),
                'logs_by_action' => [
                    'created' => $this->logRepository->countByAction('created'),
                    'updated' => $this->logRepository->countByAction('updated'),
                    'deleted' => $this->logRepository->countByAction('deleted'),
                ],
                'recent_activity' => $this->logRepository->getStatsByAction(7), // Last 7 days
            ];

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
                $log = $this->logRepository->findById($id);
                
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
            $logs = $this->logRepository->findRecent(30);

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