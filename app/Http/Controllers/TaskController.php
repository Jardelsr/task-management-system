<?php

namespace App\Http\Controllers;

use App\Repositories\TaskRepositoryInterface;
use App\Repositories\LogRepositoryInterface;
use App\Exceptions\TaskNotFoundException;
use App\Exceptions\TaskValidationException;
use App\Exceptions\TaskOperationException;
use App\Exceptions\DatabaseException;
use App\Exceptions\LoggingException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\Task;

class TaskController extends Controller
{
    /**
     * Task repository instance
     *
     * @var TaskRepositoryInterface
     */
    protected TaskRepositoryInterface $taskRepository;

    /**
     * Log repository instance
     *
     * @var LogRepositoryInterface
     */
    protected LogRepositoryInterface $logRepository;

    /**
     * TaskController constructor with dependency injection
     *
     * @param TaskRepositoryInterface $taskRepository
     * @param LogRepositoryInterface $logRepository
     */
    public function __construct(
        TaskRepositoryInterface $taskRepository,
        LogRepositoryInterface $logRepository
    ) {
        $this->taskRepository = $taskRepository;
        $this->logRepository = $logRepository;
    }

    /**
     * Display a listing of tasks with advanced filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get filter parameters
            $status = $request->query('status');
            $assignedTo = $request->query('assigned_to');
            $createdBy = $request->query('created_by');
            $overdue = $request->query('overdue');
            $withDueDate = $request->query('with_due_date');
            $sortBy = $request->query('sort_by', 'created_at');
            $sortOrder = $request->query('sort_order', 'desc');
            $limit = (int) $request->query('limit', 50);
            $page = (int) $request->query('page', 1);
            
            // Validate status parameter if provided
            if ($status && !Task::isValidStatus($status)) {
                return response()->json([
                    'error' => 'Invalid status parameter',
                    'valid_statuses' => Task::getAvailableStatuses()
                ], 400);
            }

            // Validate sort parameters
            $validSortFields = ['created_at', 'updated_at', 'due_date', 'title', 'status'];
            if (!in_array($sortBy, $validSortFields)) {
                return response()->json([
                    'error' => 'Invalid sort_by parameter',
                    'valid_sort_fields' => $validSortFields
                ], 400);
            }

            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                return response()->json([
                    'error' => 'Invalid sort_order parameter',
                    'valid_sort_orders' => ['asc', 'desc']
                ], 400);
            }

            // Validate limit (between 1 and 1000)
            $limit = max(1, min($limit, 1000));
            $page = max(1, $page);
            $offset = ($page - 1) * $limit;

            // Apply filters using repository
            $tasks = $this->applyFilters([
                'status' => $status,
                'assigned_to' => $assignedTo,
                'created_by' => $createdBy,
                'overdue' => $overdue,
                'with_due_date' => $withDueDate,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'limit' => $limit,
                'offset' => $offset
            ]);

            // Get total count for pagination
            $totalCount = $this->getTotalCount([
                'status' => $status,
                'assigned_to' => $assignedTo,
                'created_by' => $createdBy,
                'overdue' => $overdue,
                'with_due_date' => $withDueDate
            ]);

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'total_pages' => ceil($totalCount / $limit),
                    'has_next_page' => $page < ceil($totalCount / $limit),
                    'has_prev_page' => $page > 1
                ],
                'filters' => [
                    'status' => $status,
                    'assigned_to' => $assignedTo,
                    'created_by' => $createdBy,
                    'overdue' => $overdue,
                    'with_due_date' => $withDueDate,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve tasks',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified task
     *
     * @param int $id
     * @return JsonResponse
     * @throws TaskNotFoundException
     */
    public function show(int $id): JsonResponse
    {
        try {
            $task = $this->taskRepository->findById($id);

            if (!$task) {
                throw new TaskNotFoundException($id);
            }

            return $this->successResponse($task, 'Task retrieved successfully');

        } catch (TaskNotFoundException $e) {
            throw $e; // Re-throw to be handled by exception handler
        } catch (\Exception $e) {
            throw new DatabaseException(
                'Failed to retrieve task from database',
                'select',
                ['task_id' => $id]
            );
        }
    }

    /**
     * Store a newly created task
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $validatedData = $this->validateTaskData($request);

            if ($validatedData instanceof JsonResponse) {
                return $validatedData; // Return validation errors
            }

            // Create the task
            $task = $this->taskRepository->create($validatedData);

            // Log the creation
            $this->logRepository->logCreated(
                $task->id,
                $task->toArray(),
                $this->getUserInfo($request)
            );

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => $task
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create task',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified task
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            // Check if task exists
            $existingTask = $this->taskRepository->findById($id);
            
            if (!$existingTask) {
                return response()->json([
                    'error' => 'Task not found'
                ], 404);
            }

            // Store old data for logging
            $oldData = $existingTask->toArray();

            // Validate request data
            $validatedData = $this->validateTaskData($request, $id);

            if ($validatedData instanceof JsonResponse) {
                return $validatedData; // Return validation errors
            }

            // Update the task
            $updatedTask = $this->taskRepository->update($id, $validatedData);

            // Log the update
            $this->logRepository->logUpdated(
                $id,
                $oldData,
                $updatedTask->toArray(),
                $this->getUserInfo($request)
            );

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $updatedTask
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update task',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified task
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // Check if task exists
            $task = $this->taskRepository->findById($id);
            
            if (!$task) {
                return response()->json([
                    'error' => 'Task not found'
                ], 404);
            }

            // Store task data for logging
            $taskData = $task->toArray();

            // Delete the task
            $deleted = $this->taskRepository->delete($id);

            if (!$deleted) {
                return response()->json([
                    'error' => 'Failed to delete task'
                ], 500);
            }

            // Log the deletion
            $this->logRepository->logDeleted(
                $id,
                $taskData,
                $this->getUserInfo($request)
            );

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete task',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task statistics
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => $this->taskRepository->countByStatus(),
                'pending' => $this->taskRepository->countByStatus(Task::STATUS_PENDING),
                'in_progress' => $this->taskRepository->countByStatus(Task::STATUS_IN_PROGRESS),
                'completed' => $this->taskRepository->countByStatus(Task::STATUS_COMPLETED),
                'cancelled' => $this->taskRepository->countByStatus(Task::STATUS_CANCELLED),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate task data from request
     *
     * @param Request $request
     * @param int|null $taskId
     * @return array|JsonResponse
     */
    protected function validateTaskData(Request $request, ?int $taskId = null)
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => [
                'required',
                Rule::in(Task::getAvailableStatuses())
            ],
            'created_by' => 'nullable|integer|min:1',
            'assigned_to' => 'nullable|integer|min:1',
            'due_date' => 'nullable|date|after:now'
        ];

        // For updates, make fields optional
        if ($taskId) {
            $rules['title'] = 'sometimes|required|string|max:255';
            $rules['status'] = [
                'sometimes',
                'required',
                Rule::in(Task::getAvailableStatuses())
            ];
        }

        $validator = app('validator')->make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return $validator->validated();
    }

    /**
     * Get user information for logging
     *
     * @param Request $request
     * @return array
     */
    protected function getUserInfo(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Apply filters to task query
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function applyFilters(array $filters): \Illuminate\Database\Eloquent\Collection
    {
        return $this->taskRepository->findWithFilters($filters);
    }

    /**
     * Get total count of tasks matching filters
     *
     * @param array $filters
     * @return int
     */
    protected function getTotalCount(array $filters): int
    {
        return $this->taskRepository->countWithFilters($filters);
    }
}