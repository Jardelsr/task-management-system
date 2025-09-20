<?php

namespace App\Http\Controllers;

use App\Repositories\LogRepositoryInterface;
use App\Exceptions\LoggingException;
use App\Exceptions\DatabaseException;
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
            $limit = (int) $request->query('limit', 50);
            $limit = min(max($limit, 1), 1000); // Ensure limit is between 1 and 1000

            $logs = $this->logRepository->findRecent($limit);

            return response()->json([
                'success' => true,
                'data' => $logs,
                'count' => $logs->count(),
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve logs',
                'message' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'data' => $logs,
                'count' => $logs->count(),
                'task_id' => $id,
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve task logs',
                'message' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve log statistics',
                'message' => $e->getMessage()
            ], 500);
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
                    return response()->json([
                        'error' => 'Log not found',
                        'message' => 'No log found with the provided ID'
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $log
                ]);
            }

            // If no id parameter, return last 30 logs
            $logs = $this->logRepository->findRecent(30);

            return response()->json([
                'success' => true,
                'data' => $logs,
                'count' => $logs->count(),
                'message' => 'Last 30 application logs'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}