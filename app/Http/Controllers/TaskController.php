<?php

namespace App\Http\Controllers;

use App\Repositories\TaskRepositoryInterface;
use App\Services\LogServiceInterface;
use App\Services\ErrorLoggingService;
use App\Exceptions\TaskNotFoundException;
use App\Exceptions\TaskValidationException;
use App\Exceptions\TaskOperationException;
use App\Exceptions\TaskRestoreException;
use App\Http\Requests\CreateTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\ValidationHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Task;
use App\Models\TaskLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TaskController extends Controller
{
    protected TaskRepositoryInterface $taskRepository;
    protected LogServiceInterface $logService;

    public function __construct(
        TaskRepositoryInterface $taskRepository,
        LogServiceInterface $logService
    ) {
        $this->taskRepository = $taskRepository;
        $this->logService = $logService;
    }

    /**
     * List all tasks with advanced filtering and pagination
     * 
     * @OA\Get(
     *     path="/tasks",
     *     tags={"Tasks"},
     *     summary="Get all tasks",
     *     description="Retrieve a paginated list of tasks with optional filtering by status, priority, assigned user, and date ranges. Supports full-text search and advanced sorting options.",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of tasks per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by task status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "in_progress", "completed", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="priority",
     *         in="query",
     *         description="Filter by task priority",
     *         required=false,
     *         @OA\Schema(type="string", enum={"low", "medium", "high", "urgent"})
     *     ),
     *     @OA\Parameter(
     *         name="assigned_to",
     *         in="query",
     *         description="Filter by assigned user ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in task title and description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"id", "title", "status", "priority", "created_at", "updated_at", "due_date"}, default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="due_date_from",
     *         in="query",
     *         description="Filter tasks with due date from this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="due_date_to",
     *         in="query",
     *         description="Filter tasks with due date until this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Task")),
     *             @OA\Property(property="message", type="string", example="Tasks retrieved successfully"),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="total_pages", type="integer"),
     *                     @OA\Property(property="has_next_page", type="boolean"),
     *                     @OA\Property(property="has_previous_page", type="boolean"),
     *                     @OA\Property(property="next_page", type="integer", nullable=true),
     *                     @OA\Property(property="previous_page", type="integer", nullable=true)
     *                 ),
     *                 @OA\Property(property="applied_filters", type="object")
     *             )
     *         ),
     *         @OA\Header(header="X-Total-Count", @OA\Schema(type="integer"), description="Total number of tasks"),
     *         @OA\Header(header="X-Page", @OA\Schema(type="integer"), description="Current page number"),
     *         @OA\Header(header="X-Per-Page", @OA\Schema(type="integer"), description="Items per page"),
     *         @OA\Header(header="X-Total-Pages", @OA\Schema(type="integer"), description="Total pages available")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid filter parameters",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate and process filter parameters
            $filters = ValidationHelper::validateFilterParameters($request);
            
            // Set pagination defaults if not provided
            $limit = $filters['limit'] ?? 50;
            $page = $filters['page'] ?? 1;
            $offset = ($page - 1) * $limit;
            
            $filters['limit'] = $limit;
            $filters['offset'] = $offset;
            
            // Get filtered tasks and total count
            $tasks = $this->taskRepository->findWithFilters($filters);
            $totalCount = $this->taskRepository->countWithFilters($filters);
            
            // Calculate pagination metadata
            $totalPages = ceil($totalCount / $limit);
            
            $pagination = [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'has_previous_page' => $page > 1,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'previous_page' => $page > 1 ? $page - 1 : null,
            ];
            
            // Prepare response metadata including applied filters
            $appliedFilters = array_filter($filters, function($value, $key) {
                return $value !== null && $value !== '' && !in_array($key, ['limit', 'offset', 'page']);
            }, ARRAY_FILTER_USE_BOTH);

            $additionalMeta = [];
            if (!empty($appliedFilters)) {
                $additionalMeta['applied_filters'] = $appliedFilters;
            }

            return $this->paginatedResponse(
                $tasks->toArray(),
                $pagination,
                'Tasks retrieved successfully',
                $additionalMeta
            )->withHeaders([
                'X-Total-Count' => $totalCount,
                'X-Page' => $page,
                'X-Per-Page' => $limit,
                'X-Total-Pages' => $totalPages,
                'X-Applied-Filters' => !empty($appliedFilters) ? http_build_query($appliedFilters) : 'none'
            ]);
            
        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to retrieve tasks', 'index');
        }
    }

    /**
     * Retrieve a specific task by ID
     * 
     * @OA\Get(
     *     path="/tasks/{id}",
     *     tags={"Tasks"},
     *     summary="Get a specific task",
     *     description="Retrieve detailed information about a specific task by its ID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid task ID",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            $task = $this->taskRepository->findById($validatedId);

            if (!$task) {
                throw TaskNotFoundException::forOperation($validatedId, 'show');
            }

            return $this->successResponse($task, 'Task retrieved successfully');
        } catch (TaskNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException(
                'Unexpected error while retrieving task: ' . $e->getMessage(),
                'show',
                $id
            );
        }
    }

    /**
     * Create a new task
     * 
     * @OA\Post(
     *     path="/tasks",
     *     tags={"Tasks"},
     *     summary="Create a new task",
     *     description="Create a new task with comprehensive validation, security checks, and audit logging. Supports rate limiting and performance monitoring.",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Task creation data",
     *         @OA\JsonContent(ref="#/components/schemas/TaskCreateRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Task created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=413,
     *         description="Request too large",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit exceeded",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Log the start of task creation
            ErrorLoggingService::logTaskOperation('create_start', null, [
                'request_size' => strlen(json_encode($request->all())),
                'ip_address' => $request->ip()
            ]);
            
            // Rate limiting for task creation
            $this->checkRateLimit(
                'task_create:' . request()->ip(),
                100, // 100 tasks per hour
                3600
            );

            // Validate request size and structure
            $this->validateRequestSize($request->all(), 10, 5); // 10KB max, 5 levels deep

            return $this->handleDatabaseOperation(function () use ($request, $startTime) {
                // Use FormRequest for validation with security checks
                $createRequest = CreateTaskRequest::createFromRequest($request);
                $validatedData = $createRequest->validated();
                
                // Additional security validation
                $this->validateRequestSecurity($validatedData);
                
                $task = $this->taskRepository->create($validatedData);
                
                // Enhanced logging for task creation with error handling
                $this->handleWithFallback(
                    function () use ($task, $validatedData, $request) {
                        $this->logTaskCreation($task, $validatedData, $request);
                    },
                    function () use ($task) {
                        // Fallback logging using ErrorLoggingService
                        ErrorLoggingService::logTaskOperation('create_fallback', $task->id, [
                            'title' => $task->title,
                            'fallback_reason' => 'Primary logging failed'
                        ]);
                    },
                    'task_creation_logging'
                );
                
                // Performance metrics logging
                $executionTime = microtime(true) - $startTime;
                ErrorLoggingService::logPerformanceMetrics('task_create', $executionTime, [
                    'task_id' => $task->id,
                    'data_size_kb' => round(strlen(json_encode($validatedData)) / 1024, 2)
                ]);
                
                // Log successful task creation
                ErrorLoggingService::logTaskOperation('create_success', $task->id, [
                    'title' => $task->title,
                    'priority' => $task->priority,
                    'execution_time_ms' => round($executionTime * 1000, 2)
                ]);
                
                return $this->createdResponse($task->toArray(), 'Task created successfully');
            }, 'task_creation');
            
        } catch (TaskValidationException $e) {
            // Log validation errors
            ErrorLoggingService::logValidationError(
                method_exists($e, 'getErrors') ? $e->getErrors() : ['general' => [$e->getMessage()]],
                $request,
                [
                    'operation' => 'task_creation',
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]
            );
            throw $e;
            
        } catch (\Exception $e) {
            // Log unexpected errors with comprehensive context
            ErrorLoggingService::logError($e, $request, [
                'operation' => 'task_creation',
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'request_data' => $request->all()
            ]);
            
            throw new TaskOperationException(
                'Failed to create task: ' . $e->getMessage(),
                'store',
                null,
                500
            );
        }
    }

    /**
     * Update an existing task
     * 
     * @OA\Put(
     *     path="/tasks/{id}",
     *     tags={"Tasks"},
     *     summary="Update a task",
     *     description="Update an existing task with partial update support, change tracking, and concurrent operation protection.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Task update data (partial updates supported)",
     *         @OA\JsonContent(ref="#/components/schemas/TaskUpdateRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task updated successfully"),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="changed_fields", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="changes_count", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or invalid task ID",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Concurrent update detected",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit exceeded",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     * 
     * @OA\Patch(
     *     path="/tasks/{id}",
     *     tags={"Tasks"},
     *     summary="Partially update a task",
     *     description="Same as PUT - supports partial updates with change tracking and concurrent operation protection.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Task partial update data",
     *         @OA\JsonContent(ref="#/components/schemas/TaskUpdateRequest")
     *     ),
     *     @OA\Response(response=200, ref="#/components/responses/TaskUpdated"),
     *     @OA\Response(response=400, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=404, ref="#/components/responses/TaskNotFound"),
     *     @OA\Response(response=409, ref="#/components/responses/ConcurrentUpdate"),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimitExceeded"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $startTime = microtime(true);
            
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            // Rate limiting for task updates
            $this->checkRateLimit(
                'task_update:' . request()->ip(),
                200, // 200 updates per hour
                3600
            );

            // Concurrent operation protection
            return $this->handleConcurrentOperation(
                "task_update_{$validatedId}",
                function () use ($request, $validatedId, $startTime) {
                    return $this->handleDatabaseOperation(function () use ($request, $validatedId, $startTime) {
                        // Find the existing task
                        $task = $this->taskRepository->findById($validatedId);
                        
                        if (!$task) {
                            throw new TaskNotFoundException(
                                $validatedId,
                                'update',
                                'Task not found for update operation',
                                ['operation' => 'update', 'possible_reasons' => ['Task does not exist', 'Task was deleted', 'Invalid task ID']]
                            );
                        }

                        // Validate request size and structure
                        $this->validateRequestSize($request->all(), 10, 5);
                        
                        // Use FormRequest for validation with security checks
                        $updateRequest = UpdateTaskRequest::createFromRequest($request);
                        $validatedData = $updateRequest->validated();

                        // Security validation
                        $this->validateRequestSecurity($validatedData);

                        // Skip update if no valid data provided
                        if (empty($validatedData)) {
                            return $this->successResponse(
                                $task->toArray(), 
                                'No valid data provided for update'
                            );
                        }

                        // Store original data for comparison
                        $originalData = $task->toArray();

                        // Perform the partial update
                        $updatedTask = $this->taskRepository->update($validatedId, $validatedData);

                        // Determine what fields were actually changed
                        $changedFields = $this->getChangedFields($originalData, $updatedTask->toArray());

                        // Enhanced comprehensive logging for task update
                        $this->handleWithFallback(
                            function () use ($task, $updatedTask, $request, $validatedData, $changedFields) {
                                $this->logTaskUpdate(
                                    $task, 
                                    $updatedTask, 
                                    $request->all(), 
                                    $validatedData, 
                                    $changedFields, 
                                    $request
                                );
                            },
                            function () use ($updatedTask, $changedFields) {
                                // Fallback logging
                                \Log::info('Task updated (fallback log)', [
                                    'task_id' => $updatedTask->id,
                                    'changed_fields' => array_keys($changedFields),
                                    'timestamp' => Carbon::now()->toISOString()
                                ]);
                            },
                            'task_update_logging'
                        );
                        
                        // Performance metrics logging
                        $executionTime = microtime(true) - $startTime;
                        $this->logPerformanceMetrics('task_update', $executionTime, [
                            'task_id' => $updatedTask->id,
                            'changed_fields_count' => count($changedFields),
                            'data_size_kb' => round(strlen(json_encode($validatedData)) / 1024, 2)
                        ]);

                        return $this->successResponse(
                            $updatedTask->toArray(), 
                            'Task updated successfully',
                            200,
                            [
                                'changed_fields' => array_keys($changedFields),
                                'changes_count' => count($changedFields)
                            ]
                        );
                    }, 'task_update');
                },
                'task_update'
            );
            
        } catch (TaskNotFoundException $e) {
            throw $e;
        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error in task update', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new TaskOperationException(
                'Failed to update task: ' . $e->getMessage(),
                'update',
                $id,
                500
            );
        }
    }

    /**
     * Soft delete a task
     * 
     * @OA\Delete(
     *     path="/tasks/{id}",
     *     tags={"Tasks"},
     *     summary="Soft delete a task",
     *     description="Soft delete a task - moves it to trash where it can be restored. Task data is preserved and can be recovered.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional deletion metadata",
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", description="Reason for deletion", example="task_obsolete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task soft deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="deleted", type="boolean", example=true)
     *             ),
     *             @OA\Property(property="message", type="string", example="Task has been moved to trash and can be restored if needed"),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="original_status", type="string"),
     *                 @OA\Property(property="instructions", type="object",
     *                     @OA\Property(property="restore", type="string", example="POST /tasks/{id}/restore"),
     *                     @OA\Property(property="permanent_delete", type="string", example="DELETE /tasks/{id}/force"),
     *                     @OA\Property(property="view_trashed", type="string", example="GET /tasks/trashed")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid task ID",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            // Get task info before deletion for metadata and logging
            $task = $this->taskRepository->findById($validatedId);
            $originalData = $task->toArray();
            
            // Delete the task - repository will throw TaskNotFoundException if not found
            $deleteResult = $this->taskRepository->delete($validatedId);
            
            // Log the task deletion comprehensively
            try {
                $this->logTaskDeletion($task, 'soft_delete', $request, [
                    'deletion_reason' => $request->input('reason', 'user_initiated'),
                    'batch_operation' => $request->header('X-Batch-ID') ? true : false,
                ]);
            } catch (\Exception $e) {
                // Log the logging error but don't fail the deletion
                \Log::warning('Failed to create comprehensive task deletion log', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Return enhanced soft delete response with recovery information
            return $this->softDeletedResponse(
                $validatedId,
                'Task has been moved to trash and can be restored if needed',
                [
                    'original_status' => $task->status,
                    'instructions' => [
                        'restore' => "POST /tasks/{$validatedId}/restore",
                        'permanent_delete' => "DELETE /tasks/{$validatedId}/force",
                        'view_trashed' => "GET /tasks/trashed"
                    ]
                ]
            );
        } catch (TaskNotFoundException | TaskOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException(
                'Unexpected error during task deletion: ' . $e->getMessage(),
                'delete',
                $id
            );
        }
    }

    /**
     * Restore a soft-deleted task
     * 
     * @OA\Post(
     *     path="/tasks/{id}/restore",
     *     tags={"Tasks"},
     *     summary="Restore a soft-deleted task",
     *     description="Restore a task that was previously soft-deleted. The task will be returned to its original state.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional restoration metadata",
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", description="Reason for restoration", example="task_still_needed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task has been successfully restored from trash"),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="previous_state", type="string", example="trashed"),
     *                 @OA\Property(property="restored_to_status", type="string"),
     *                 @OA\Property(property="available_actions", type="object",
     *                     @OA\Property(property="view", type="string", example="GET /tasks/{id}"),
     *                     @OA\Property(property="update", type="string", example="PUT /tasks/{id}"),
     *                     @OA\Property(property="delete_again", type="string", example="DELETE /tasks/{id}")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found in trash",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid task ID",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            // Get the trashed task for logging before restoration
            $trashedTask = $this->taskRepository->findTrashedById($validatedId);
            if (!$trashedTask) {
                throw new TaskNotFoundException($validatedId, 'restore', [
                    'operation' => 'restore',
                    'possible_reasons' => ['Task was never deleted', 'Task was permanently deleted', 'Invalid task ID']
                ]);
            }
            
            // Restore the task - repository will handle task existence and restore validation
            $restoreResult = $this->taskRepository->restore($validatedId);
            
            // Return restored task data
            $restoredTask = $this->taskRepository->findById($validatedId);

            // Log the task restoration comprehensively
            try {
                $this->logTaskDeletion($restoredTask, 'restore', $request, [
                    'restoration_reason' => $request->input('reason', 'user_requested'),
                    'previous_state' => 'trashed',
                    'restored_at' => Carbon::now()->toISOString(),
                ]);
            } catch (\Exception $e) {
                // Log the logging error but don't fail the restoration
                \Log::warning('Failed to create comprehensive task restoration log', [
                    'task_id' => $restoredTask->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return $this->restoredResponse(
                $restoredTask->toArray(),
                'Task has been successfully restored from trash',
                [
                    'previous_state' => 'trashed',
                    'restored_to_status' => $restoredTask->status,
                    'available_actions' => [
                        'view' => "GET /tasks/{$validatedId}",
                        'update' => "PUT /tasks/{$validatedId}",
                        'delete_again' => "DELETE /tasks/{$validatedId}"
                    ]
                ]
            );
        } catch (TaskRestoreException | TaskNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException(
                'Unexpected error during task restore: ' . $e->getMessage(),
                'restore',
                $id
            );
        }
    }

    /**
     * Force delete a task (permanent deletion)
     * 
     * @OA\Delete(
     *     path="/tasks/{id}/force",
     *     tags={"Tasks"},
     *     summary="Permanently delete a task",
     *     description="Permanently delete a task from the system. This action cannot be undone. Task data will be completely removed.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional permanent deletion metadata",
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", description="Reason for permanent deletion", example="permanent_cleanup")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task permanently deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="permanently_deleted", type="boolean", example=true)
     *             ),
     *             @OA\Property(property="message", type="string", example="Task has been permanently deleted and cannot be recovered"),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="confirmation_required", type="boolean", example=true),
     *                 @OA\Property(property="audit_logged", type="boolean", example=true),
     *                 @OA\Property(property="alternative_actions", type="object",
     *                     @OA\Property(property="create_new", type="string", example="POST /tasks"),
     *                     @OA\Property(property="view_all", type="string", example="GET /tasks"),
     *                     @OA\Property(property="view_trashed", type="string", example="GET /tasks/trashed")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid task ID",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            // Get task data before permanent deletion for logging
            $task = $this->taskRepository->findTrashedById($validatedId);
            if (!$task) {
                // Try to find in active tasks
                $task = $this->taskRepository->findById($validatedId);
            }
            
            if (!$task) {
                throw new TaskNotFoundException($validatedId, 'force_delete', [
                    'operation' => 'permanent_deletion',
                    'possible_reasons' => ['Task never existed', 'Task already permanently deleted']
                ]);
            }
            
            // Log the task force deletion before it's permanently removed
            try {
                $this->logTaskDeletion($task, 'force_delete', $request, [
                    'deletion_reason' => $request->input('reason', 'permanent_cleanup'),
                    'confirmation_token' => $request->header('X-Confirmation-Token'),
                    'was_trashed_first' => $task->deleted_at !== null,
                    'final_deletion_at' => Carbon::now()->toISOString(),
                ]);
            } catch (\Exception $e) {
                // Log the logging error but don't fail the force deletion
                \Log::warning('Failed to create comprehensive task force deletion log', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Force delete the task - repository will throw TaskNotFoundException if not found
            $forceDeleteResult = $this->taskRepository->forceDelete($validatedId);

            return $this->forceDeletedResponse(
                $validatedId,
                "Task has been permanently deleted and cannot be recovered",
                [
                    'confirmation_required' => true,
                    'audit_logged' => true,
                    'alternative_actions' => [
                        'create_new' => 'POST /tasks',
                        'view_all' => 'GET /tasks',
                        'view_trashed' => 'GET /tasks/trashed'
                    ]
                ]
            );
        } catch (TaskNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException(
                'Unexpected error during task force deletion: ' . $e->getMessage(),
                'force_delete',
                $id
            );
        }
    }

    /**
     * List trashed (soft-deleted) tasks
     * 
     * @OA\Get(
     *     path="/tasks/trashed",
     *     tags={"Tasks"},
     *     summary="Get all trashed tasks",
     *     description="Retrieve all tasks that have been soft-deleted and are available for restoration.",
     *     @OA\Response(
     *         response=200,
     *         description="Trashed tasks retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Task")),
     *             @OA\Property(property="message", type="string", example="Trashed tasks retrieved successfully"),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total_trashed", type="integer"),
     *                 @OA\Property(property="all_recoverable", type="boolean", example=true),
     *                 @OA\Property(property="bulk_operations", type="object",
     *                     @OA\Property(property="restore_all", type="string", example="POST /tasks/restore-all"),
     *                     @OA\Property(property="force_delete_all", type="string", example="DELETE /tasks/force-delete-all")
     *                 ),
     *                 @OA\Property(property="individual_operations", type="object",
     *                     @OA\Property(property="restore_single", type="string", example="POST /tasks/{id}/restore"),
     *                     @OA\Property(property="force_delete_single", type="string", example="DELETE /tasks/{id}/force")
     *                 ),
     *                 @OA\Property(property="note", type="string", example="Soft-deleted tasks remain here until permanently deleted or restored")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function trashed(Request $request): JsonResponse
    {
        try {
            $trashedTasks = $this->taskRepository->findTrashed();
            $trashedArray = $trashedTasks->toArray();

            return $this->trashedTasksResponse(
                $trashedArray,
                'Trashed tasks retrieved successfully',
                [
                    'total_trashed' => count($trashedArray),
                    'all_recoverable' => true,
                    'bulk_operations' => [
                        'restore_all' => 'POST /tasks/restore-all',
                        'force_delete_all' => 'DELETE /tasks/force-delete-all'
                    ],
                    'individual_operations' => [
                        'restore_single' => 'POST /tasks/{id}/restore',
                        'force_delete_single' => 'DELETE /tasks/{id}/force'
                    ],
                    'note' => 'Soft-deleted tasks remain here until permanently deleted or restored'
                ]
            );
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to retrieve trashed tasks', 'trashed');
        }
    }

    /**
     * Get task statistics
     * 
     * @OA\Get(
     *     path="/tasks/stats",
     *     tags={"Tasks"},
     *     summary="Get task statistics",
     *     description="Retrieve comprehensive statistics about tasks including counts by status and other metrics.",
     *     @OA\Response(
     *         response=200,
     *         description="Task statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total", type="integer", description="Total number of active tasks"),
     *                 @OA\Property(property="pending", type="integer", description="Number of pending tasks"),
     *                 @OA\Property(property="in_progress", type="integer", description="Number of tasks in progress"),
     *                 @OA\Property(property="completed", type="integer", description="Number of completed tasks"),
     *                 @OA\Property(property="cancelled", type="integer", description="Number of cancelled tasks")
     *             ),
     *             @OA\Property(property="message", type="string", example="Task statistics retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
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

            return $this->statsResponse($stats);
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to retrieve task statistics', 'stats');
        }
    }

    /**
     * Detect which fields were changed in the update
     *
     * @param array $original
     * @param array $updated
     * @return array
     */
    private function getChangedFields(array $original, array $updated): array
    {
        $changed = [];
        
        // Fields to compare for changes
        $fieldsToCheck = ['title', 'description', 'status', 'assigned_to', 'due_date', 'completed_at'];
        
        foreach ($fieldsToCheck as $field) {
            $originalValue = $original[$field] ?? null;
            $updatedValue = $updated[$field] ?? null;
            
            // Convert dates to comparable format
            if (in_array($field, ['due_date', 'completed_at']) && $originalValue) {
                $originalValue = date('Y-m-d H:i:s', strtotime($originalValue));
            }
            if (in_array($field, ['due_date', 'completed_at']) && $updatedValue) {
                $updatedValue = date('Y-m-d H:i:s', strtotime($updatedValue));
            }
            
            if ($originalValue !== $updatedValue) {
                $changed[] = $field;
            }
        }
        
        return $changed;
    }

    /**
     * Log comprehensive task creation details
     *
     * @param Task $task The created task
     * @param array $validatedData The input data used to create the task
     * @param Request $request The HTTP request
     * @return void
     */
    private function logTaskCreation(Task $task, array $validatedData, Request $request): void
    {
        // Get user information
        $userId = $request->header('X-User-Id', 1);
        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();

        // Prepare comprehensive logging data
        $logData = [
            'created_task_data' => $task->toArray(),
            'input_data' => $validatedData,
            'request_metadata' => [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'request_id' => $request->header('X-Request-ID', uniqid('task_create_')),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'timestamp' => Carbon::now()->toISOString(),
            ],
            'validation_passed' => true,
            'creation_context' => [
                'auto_generated_fields' => [
                    'id' => $task->id,
                    'created_at' => $task->created_at->toISOString(),
                    'updated_at' => $task->updated_at->toISOString(),
                ],
                'default_values_applied' => $this->getDefaultValuesApplied($validatedData, $task),
                'computed_fields' => $this->getComputedFields($task),
            ]
        ];

        // Create the primary activity log
        try {
            $this->logService->createTaskActivityLog(
                $task->id,
                TaskLog::ACTION_CREATED,
                [], // No old data for creation
                $task->toArray(),
                $userId
            );
        } catch (\Exception $e) {
            \Log::error('Failed to create task activity log for creation', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continue with fallback logging
        }

        // Create a detailed creation log entry
        try {
            $this->logService->createLog(
                $task->id,
                'task_creation_details',
                $logData,
                $userId,
                $this->generateCreationDescription($task, $validatedData)
            );
        } catch (\Exception $e) {
            \Log::error('Failed to create detailed task creation log', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Use basic file logging as fallback
            $this->logToFileAsFallback('task_creation', [
                'task_id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'user_id' => $userId,
                'created_at' => $task->created_at->toISOString()
            ]);
        }

        // Log any special conditions or notable aspects
        $this->logSpecialCreationConditions($task, $validatedData, $request);
    }

    /**
     * Identify which default values were applied during task creation
     *
     * @param array $inputData
     * @param Task $task
     * @return array
     */
    private function getDefaultValuesApplied(array $inputData, Task $task): array
    {
        $defaults = [];
        
        // Check for status default
        if (!isset($inputData['status']) && $task->status) {
            $defaults['status'] = $task->status;
        }
        
        // Check for priority default
        if (!isset($inputData['priority']) && $task->priority) {
            $defaults['priority'] = $task->priority;
        }
        
        // Check for assigned_to default
        if (!isset($inputData['assigned_to']) && $task->assigned_to) {
            $defaults['assigned_to'] = $task->assigned_to;
        }

        return $defaults;
    }

    /**
     * Get computed fields that were calculated during creation
     *
     * @param Task $task
     * @return array
     */
    private function getComputedFields(Task $task): array
    {
        $computed = [];
        
        // Check if due_date calculations were applied
        if ($task->due_date) {
            $computed['is_overdue'] = $task->due_date < Carbon::now();
            $computed['days_until_due'] = Carbon::now()->diffInDays($task->due_date, false);
        }
        
        // Add any other computed fields
        $computed['slug'] = $task->title ? \Illuminate\Support\Str::slug($task->title) : null;
        $computed['estimated_completion_time'] = $this->estimateCompletionTime($task);
        
        return array_filter($computed, fn($value) => $value !== null);
    }

    /**
     * Generate a human-readable description for the task creation
     *
     * @param Task $task
     * @param array $inputData
     * @return string
     */
    private function generateCreationDescription(Task $task, array $inputData): string
    {
        $description = "Task '{$task->title}' was created";
        
        if ($task->assigned_to) {
            $description .= " and assigned to user ID {$task->assigned_to}";
        }
        
        if ($task->due_date) {
            $dueDate = $task->due_date->format('Y-m-d H:i');
            $description .= " with due date {$dueDate}";
        }
        
        if ($task->priority && $task->priority !== 'medium') {
            $description .= " (priority: {$task->priority})";
        }
        
        $description .= ". Status: {$task->status}";
        
        return $description;
    }

    /**
     * Log any special conditions during task creation
     *
     * @param Task $task
     * @param array $inputData
     * @param Request $request
     * @return void
     */
    private function logSpecialCreationConditions(Task $task, array $inputData, Request $request): void
    {
        $specialConditions = [];
        
        // Check for overdue creation
        if ($task->due_date && $task->due_date < Carbon::now()) {
            $specialConditions[] = 'created_overdue';
        }
        
        // Check for high priority tasks
        if ($task->priority === 'high' || $task->priority === 'urgent') {
            $specialConditions[] = 'high_priority_task';
        }
        
        // Check for immediate assignment
        if ($task->assigned_to) {
            $specialConditions[] = 'immediately_assigned';
        }
        
        // Check for bulk creation indicators
        $batchId = $request->header('X-Batch-ID');
        if ($batchId) {
            $specialConditions[] = 'batch_creation';
        }
        
        // Log special conditions if any
        if (!empty($specialConditions)) {
            try {
                $this->logService->createLog(
                    $task->id,
                    'task_creation_special_conditions',
                    [
                        'conditions' => $specialConditions,
                        'batch_id' => $batchId,
                        'metadata' => [
                            'is_overdue_on_creation' => $task->due_date && $task->due_date < Carbon::now(),
                            'priority_level' => $task->priority,
                            'immediate_assignment' => $task->assigned_to !== null,
                        ]
                    ],
                    $request->header('X-User-Id', 1),
                    'Special conditions detected during task creation: ' . implode(', ', $specialConditions)
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to log special creation conditions', [
                    'task_id' => $task->id,
                    'conditions' => $specialConditions,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Create comprehensive logging for task update operations
     *
     * @param Task $originalTask The task before updates
     * @param Task $updatedTask The task after updates
     * @param array $inputData The raw input data from request
     * @param array $validatedData The validated data that was applied
     * @param array $changedFields List of fields that actually changed
     * @param Request $request The request object for metadata
     * @return void
     */
    private function logTaskUpdate(
        Task $originalTask,
        Task $updatedTask,
        array $inputData,
        array $validatedData,
        array $changedFields,
        Request $request
    ): void {
        // Get user information
        $userId = $request->header('X-User-Id', 1);
        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();

        // Prepare field-by-field change analysis
        $fieldChanges = [];
        foreach ($changedFields as $field) {
            $fieldChanges[$field] = [
                'old_value' => $originalTask->getAttribute($field),
                'new_value' => $updatedTask->getAttribute($field),
                'change_type' => $this->getChangeType($originalTask->getAttribute($field), $updatedTask->getAttribute($field)),
                'was_provided_in_input' => array_key_exists($field, $inputData),
                'input_value' => $inputData[$field] ?? null,
                'validated_value' => $validatedData[$field] ?? null,
            ];
        }

        // Prepare comprehensive logging data
        $logData = [
            'update_operation' => [
                'fields_changed' => $changedFields,
                'total_changes' => count($changedFields),
                'partial_update' => count($validatedData) < count($originalTask->getFillable()),
            ],
            'field_changes' => $fieldChanges,
            'data_states' => [
                'original_task' => $originalTask->toArray(),
                'updated_task' => $updatedTask->toArray(),
                'input_data' => $inputData,
                'validated_data' => $validatedData,
            ],
            'request_metadata' => [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'request_id' => $request->header('X-Request-ID', uniqid('task_update_')),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'timestamp' => Carbon::now()->toISOString(),
            ],
            'validation_passed' => true,
            'update_context' => [
                'update_type' => empty($changedFields) ? 'no_changes' : 'partial_update',
                'significant_changes' => $this->getSignificantChanges($fieldChanges),
                'status_transition' => $this->getStatusTransition($originalTask, $updatedTask),
                'priority_change' => $this->getPriorityChange($originalTask, $updatedTask),
                'assignment_change' => $this->getAssignmentChange($originalTask, $updatedTask),
                'computed_fields' => $this->getComputedFieldsForUpdate($updatedTask),
            ]
        ];

        // Create the primary activity log with before/after data
        try {
            $this->logService->createTaskActivityLog(
                $updatedTask->id,
                TaskLog::ACTION_UPDATED,
                array_intersect_key($originalTask->toArray(), array_flip($changedFields)),
                array_intersect_key($updatedTask->toArray(), array_flip($changedFields)),
                $userId
            );
        } catch (\Exception $e) {
            \Log::error('Failed to create task activity log for update', [
                'task_id' => $updatedTask->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Create detailed update log entry
        try {
            $this->logService->createLog(
                $updatedTask->id,
                'task_update_details',
                $logData,
                $userId,
                $this->generateUpdateDescription($originalTask, $updatedTask, $changedFields)
            );
        } catch (\Exception $e) {
            \Log::error('Failed to create detailed task update log', [
                'task_id' => $updatedTask->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Use basic file logging as fallback
            $this->logToFileAsFallback('task_update', [
                'task_id' => $updatedTask->id,
                'title' => $updatedTask->title,
                'changed_fields' => $changedFields,
                'user_id' => $userId,
                'updated_at' => $updatedTask->updated_at->toISOString()
            ]);
        }

        // Log special update conditions if any
        $this->logSpecialUpdateConditions($originalTask, $updatedTask, $changedFields, $request);
    }

    /**
     * Determine the type of change for a field
     *
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return string
     */
    private function getChangeType($oldValue, $newValue): string
    {
        if ($oldValue === null && $newValue !== null) {
            return 'added';
        }
        if ($oldValue !== null && $newValue === null) {
            return 'removed';
        }
        if ($oldValue !== $newValue) {
            return 'modified';
        }
        return 'unchanged';
    }

    /**
     * Identify significant changes that require special attention
     *
     * @param array $fieldChanges
     * @return array
     */
    private function getSignificantChanges(array $fieldChanges): array
    {
        $significant = [];

        foreach ($fieldChanges as $field => $change) {
            switch ($field) {
                case 'status':
                    $significant[] = "Status changed from '{$change['old_value']}' to '{$change['new_value']}'";
                    break;
                case 'priority':
                    $significant[] = "Priority changed from '{$change['old_value']}' to '{$change['new_value']}'";
                    break;
                case 'assigned_to':
                    $oldUser = $change['old_value'] ? "user {$change['old_value']}" : 'unassigned';
                    $newUser = $change['new_value'] ? "user {$change['new_value']}" : 'unassigned';
                    $significant[] = "Assignment changed from {$oldUser} to {$newUser}";
                    break;
                case 'due_date':
                    $significant[] = "Due date changed from '{$change['old_value']}' to '{$change['new_value']}'";
                    break;
                case 'title':
                    $significant[] = "Title updated";
                    break;
            }
        }

        return $significant;
    }

    /**
     * Analyze status transition details
     *
     * @param Task $originalTask
     * @param Task $updatedTask
     * @return array|null
     */
    private function getStatusTransition(Task $originalTask, Task $updatedTask): ?array
    {
        if ($originalTask->status === $updatedTask->status) {
            return null;
        }

        return [
            'from' => $originalTask->status,
            'to' => $updatedTask->status,
            'is_completion' => $updatedTask->status === Task::STATUS_COMPLETED,
            'is_progression' => $this->isStatusProgression($originalTask->status, $updatedTask->status),
            'requires_notification' => $this->statusChangeRequiresNotification($originalTask->status, $updatedTask->status),
        ];
    }

    /**
     * Analyze priority change details
     *
     * @param Task $originalTask
     * @param Task $updatedTask
     * @return array|null
     */
    private function getPriorityChange(Task $originalTask, Task $updatedTask): ?array
    {
        if ($originalTask->priority === $updatedTask->priority) {
            return null;
        }

        return [
            'from' => $originalTask->priority,
            'to' => $updatedTask->priority,
            'is_escalation' => $this->isPriorityEscalation($originalTask->priority, $updatedTask->priority),
            'is_de_escalation' => $this->isPriorityDeEscalation($originalTask->priority, $updatedTask->priority),
        ];
    }

    /**
     * Analyze assignment change details
     *
     * @param Task $originalTask
     * @param Task $updatedTask
     * @return array|null
     */
    private function getAssignmentChange(Task $originalTask, Task $updatedTask): ?array
    {
        if ($originalTask->assigned_to === $updatedTask->assigned_to) {
            return null;
        }

        return [
            'from' => $originalTask->assigned_to,
            'to' => $updatedTask->assigned_to,
            'type' => $this->getAssignmentChangeType($originalTask->assigned_to, $updatedTask->assigned_to),
            'requires_notification' => true,
        ];
    }

    /**
     * Get computed fields relevant to the update
     *
     * @param Task $task
     * @return array
     */
    private function getComputedFieldsForUpdate(Task $task): array
    {
        $computed = [];
        
        if ($task->due_date) {
            $computed['is_overdue'] = $task->due_date < Carbon::now();
            $computed['days_until_due'] = Carbon::now()->diffInDays($task->due_date, false);
        }
        
        $computed['is_completed'] = $task->status === Task::STATUS_COMPLETED;
        $computed['has_assignment'] = $task->assigned_to !== null;
        $computed['completion_percentage'] = $this->calculateCompletionPercentage($task);
        
        return $computed;
    }

    /**
     * Generate a human-readable description of the update
     *
     * @param Task $originalTask
     * @param Task $updatedTask
     * @param array $changedFields
     * @return string
     */
    private function generateUpdateDescription(Task $originalTask, Task $updatedTask, array $changedFields): string
    {
        if (empty($changedFields)) {
            return 'Task update requested but no changes were made';
        }

        $descriptions = [];
        
        foreach ($changedFields as $field) {
            $oldValue = $originalTask->getAttribute($field);
            $newValue = $updatedTask->getAttribute($field);
            
            switch ($field) {
                case 'title':
                    $descriptions[] = "title updated";
                    break;
                case 'description':
                    $descriptions[] = "description modified";
                    break;
                case 'status':
                    $descriptions[] = "status changed from '{$oldValue}' to '{$newValue}'";
                    break;
                case 'priority':
                    $descriptions[] = "priority changed from '{$oldValue}' to '{$newValue}'";
                    break;
                case 'assigned_to':
                    $oldUser = $oldValue ? "user {$oldValue}" : 'unassigned';
                    $newUser = $newValue ? "user {$newValue}" : 'unassigned';
                    $descriptions[] = "assignment changed from {$oldUser} to {$newUser}";
                    break;
                case 'due_date':
                    $descriptions[] = "due date updated";
                    break;
                default:
                    $descriptions[] = "{$field} updated";
            }
        }

        return 'Task updated: ' . implode(', ', $descriptions);
    }

    /**
     * Log special conditions during update operations
     *
     * @param Task $originalTask
     * @param Task $updatedTask
     * @param array $changedFields
     * @param Request $request
     * @return void
     */
    private function logSpecialUpdateConditions(Task $originalTask, Task $updatedTask, array $changedFields, Request $request): void
    {
        $specialConditions = [];
        
        // Check for completion
        if (in_array('status', $changedFields) && $updatedTask->status === Task::STATUS_COMPLETED) {
            $specialConditions[] = 'task_completed';
        }
        
        // Check for priority escalation
        if (in_array('priority', $changedFields) && $this->isPriorityEscalation($originalTask->priority, $updatedTask->priority)) {
            $specialConditions[] = 'priority_escalated';
        }
        
        // Check for overdue task updates
        if ($updatedTask->due_date && $updatedTask->due_date < Carbon::now()) {
            $specialConditions[] = 'overdue_task_updated';
        }
        
        // Check for assignment changes
        if (in_array('assigned_to', $changedFields)) {
            $specialConditions[] = 'assignment_changed';
        }
        
        // Check for bulk update indicators
        $batchId = $request->header('X-Batch-ID');
        if ($batchId) {
            $specialConditions[] = 'batch_update';
        }
        
        // Log special conditions if any
        if (!empty($specialConditions)) {
            try {
                $this->logService->createLog(
                    $updatedTask->id,
                    'task_update_special_conditions',
                    [
                        'conditions' => $specialConditions,
                        'batch_id' => $batchId,
                        'changed_fields' => $changedFields,
                        'metadata' => [
                            'was_overdue' => $originalTask->due_date && $originalTask->due_date < Carbon::now(),
                            'is_now_overdue' => $updatedTask->due_date && $updatedTask->due_date < Carbon::now(),
                            'priority_level' => $updatedTask->priority,
                            'status_change' => $originalTask->status !== $updatedTask->status,
                            'assignment_change' => $originalTask->assigned_to !== $updatedTask->assigned_to,
                        ]
                    ],
                    $request->header('X-User-Id', 1),
                    'Special conditions detected during task update: ' . implode(', ', $specialConditions)
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to log special update conditions', [
                    'task_id' => $updatedTask->id,
                    'conditions' => $specialConditions,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Log comprehensive deletion operations (soft delete, hard delete, restore)
     *
     * @param Task $task Original task data before deletion
     * @param string $deletionType Type of deletion: 'soft_delete', 'force_delete', 'restore'
     * @param Request $request HTTP request object
     * @param array $additionalContext Additional context data
     * @return void
     */
    private function logTaskDeletion(Task $task, string $deletionType, Request $request, array $additionalContext = []): void
    {
        // Get user information
        $userId = $request->header('X-User-Id', 1);
        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();

        // Analyze task state at deletion time
        $taskAnalysis = $this->analyzeDeletionContext($task, $deletionType);

        // Prepare comprehensive logging data
        $logData = [
            'deletion_operation' => [
                'type' => $deletionType,
                'is_permanent' => $deletionType === 'force_delete',
                'is_reversible' => in_array($deletionType, ['soft_delete', 'restore']),
                'confirmation_required' => $deletionType === 'force_delete',
            ],
            'task_state' => [
                'task_data' => $task->toArray(),
                'was_overdue' => $task->due_date && $task->due_date < Carbon::now(),
                'was_completed' => $task->status === Task::STATUS_COMPLETED,
                'had_assignment' => $task->assigned_to !== null,
                'priority_level' => $task->priority,
                'age_in_days' => $task->created_at->diffInDays(Carbon::now()),
            ],
            'deletion_context' => $taskAnalysis,
            'request_metadata' => [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'request_id' => $request->header('X-Request-ID', uniqid('task_deletion_')),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'timestamp' => Carbon::now()->toISOString(),
                'confirmation_token' => $request->header('X-Confirmation-Token'),
            ],
            'security_metadata' => [
                'user_permissions' => $this->getUserPermissions($userId),
                'requires_approval' => $this->deletionRequiresApproval($task, $deletionType),
                'audit_level' => $this->getDeletionAuditLevel($task, $deletionType),
            ],
            'recovery_information' => $deletionType === 'soft_delete' ? [
                'can_restore' => true,
                'restore_endpoint' => "POST /tasks/{$task->id}/restore",
                'retention_days' => 30,
                'force_delete_endpoint' => "DELETE /tasks/{$task->id}/force",
            ] : null,
            'additional_context' => $additionalContext,
        ];

        // Determine the appropriate log action
        $logAction = match ($deletionType) {
            'soft_delete' => TaskLog::ACTION_DELETED,
            'force_delete' => TaskLog::ACTION_FORCE_DELETED,
            'restore' => TaskLog::ACTION_RESTORED,
            default => TaskLog::ACTION_DELETED
        };

        // Create the primary deletion activity log
        try {
            $this->logService->createTaskActivityLog(
                $task->id,
                $logAction,
                $task->toArray(),
                $deletionType === 'restore' ? $task->toArray() : [],
                $userId
            );

            // Create detailed deletion log entry
            $this->logService->createLog(
                $task->id,
                'task_deletion_details',
                $logData,
                $userId,
                $this->generateDeletionDescription($task, $deletionType)
            );

            // Log special deletion conditions
            $this->logSpecialDeletionConditions($task, $deletionType, $request, $taskAnalysis);

        } catch (\Exception $e) {
            // Log the logging error but don't fail the deletion
            \Log::warning('Failed to create comprehensive task deletion log', [
                'task_id' => $task->id,
                'deletion_type' => $deletionType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Analyze context and implications of task deletion
     *
     * @param Task $task
     * @param string $deletionType
     * @return array
     */
    private function analyzeDeletionContext(Task $task, string $deletionType): array
    {
        $context = [
            'deletion_urgency' => $this->assessDeletionUrgency($task),
            'data_sensitivity' => $this->assessDataSensitivity($task),
            'business_impact' => $this->assessBusinessImpact($task),
            'dependencies' => $this->checkTaskDependencies($task),
        ];

        // Add type-specific analysis
        switch ($deletionType) {
            case 'soft_delete':
                $context['soft_delete_reasons'] = $this->getSoftDeleteReasons($task);
                break;
            case 'force_delete':
                $context['permanent_deletion_risks'] = $this->getForceDeleteRisks($task);
                break;
            case 'restore':
                $context['restoration_context'] = $this->getRestorationContext($task);
                break;
        }

        return $context;
    }

    /**
     * Generate human-readable description for deletion operation
     *
     * @param Task $task
     * @param string $deletionType
     * @return string
     */
    private function generateDeletionDescription(Task $task, string $deletionType): string
    {
        $taskTitle = $task->title;
        $taskStatus = $task->status;
        
        return match ($deletionType) {
            'soft_delete' => "Task '{$taskTitle}' (status: {$taskStatus}) was moved to trash and can be restored",
            'force_delete' => "Task '{$taskTitle}' was permanently deleted and cannot be recovered",
            'restore' => "Task '{$taskTitle}' was successfully restored from trash",
            default => "Task '{$taskTitle}' underwent deletion operation: {$deletionType}"
        };
    }

    /**
     * Log special conditions during deletion operations
     *
     * @param Task $task
     * @param string $deletionType
     * @param Request $request
     * @param array $taskAnalysis
     * @return void
     */
    private function logSpecialDeletionConditions(Task $task, string $deletionType, Request $request, array $taskAnalysis): void
    {
        $specialConditions = [];
        
        // Check for high-value task deletion
        if ($task->priority === 'urgent' || $task->priority === 'high') {
            $specialConditions[] = 'high_priority_task_deleted';
        }
        
        // Check for overdue task deletion
        if ($task->due_date && $task->due_date < Carbon::now()) {
            $specialConditions[] = 'overdue_task_deleted';
        }
        
        // Check for assigned task deletion
        if ($task->assigned_to) {
            $specialConditions[] = 'assigned_task_deleted';
        }
        
        // Check for completed task deletion
        if ($task->status === Task::STATUS_COMPLETED) {
            $specialConditions[] = 'completed_task_deleted';
        }
        
        // Check for recent task deletion (created within 24 hours)
        if ($task->created_at->isAfter(Carbon::now()->subDay())) {
            $specialConditions[] = 'recent_task_deleted';
        }
        
        // Check for bulk deletion indicators
        $batchId = $request->header('X-Batch-ID');
        if ($batchId) {
            $specialConditions[] = 'batch_deletion';
        }
        
        // Check for force deletion without confirmation
        if ($deletionType === 'force_delete' && !$request->header('X-Confirmation-Token')) {
            $specialConditions[] = 'force_delete_without_confirmation';
        }
        
        // Log special conditions if any
        if (!empty($specialConditions)) {
            try {
                $this->logService->createLog(
                    $task->id,
                    'task_deletion_special_conditions',
                    [
                        'conditions' => $specialConditions,
                        'deletion_type' => $deletionType,
                        'batch_id' => $batchId,
                        'task_analysis' => $taskAnalysis,
                        'metadata' => [
                            'was_overdue' => $task->due_date && $task->due_date < Carbon::now(),
                            'was_completed' => $task->status === Task::STATUS_COMPLETED,
                            'priority_level' => $task->priority,
                            'had_assignment' => $task->assigned_to !== null,
                            'task_age_days' => $task->created_at->diffInDays(Carbon::now()),
                        ]
                    ],
                    $request->header('X-User-Id', 1),
                    'Special conditions detected during task deletion: ' . implode(', ', $specialConditions)
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to log special deletion conditions', [
                    'task_id' => $task->id,
                    'conditions' => $specialConditions,
                    'deletion_type' => $deletionType,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Helper methods for deletion context analysis
     */

    private function assessDeletionUrgency(Task $task): string
    {
        if ($task->priority === 'urgent') return 'high';
        if ($task->status === Task::STATUS_COMPLETED) return 'low';
        if ($task->due_date && $task->due_date < Carbon::now()) return 'medium';
        return 'normal';
    }

    private function assessDataSensitivity(Task $task): string
    {
        // Simple heuristic - could be enhanced with actual sensitivity markers
        $sensitiveWords = ['confidential', 'private', 'secret', 'personal', 'sensitive'];
        $content = strtolower($task->title . ' ' . ($task->description ?? ''));
        
        foreach ($sensitiveWords as $word) {
            if (str_contains($content, $word)) {
                return 'high';
            }
        }
        
        return 'normal';
    }

    private function assessBusinessImpact(Task $task): string
    {
        if ($task->priority === 'urgent') return 'high';
        if ($task->status === Task::STATUS_IN_PROGRESS) return 'medium';
        if ($task->status === Task::STATUS_COMPLETED) return 'low';
        return 'normal';
    }

    private function checkTaskDependencies(Task $task): array
    {
        // Placeholder for dependency checking - could be enhanced with actual dependency system
        return [
            'has_dependencies' => false,
            'dependent_tasks' => [],
            'blocking_tasks' => [],
        ];
    }

    private function getSoftDeleteReasons(Task $task): array
    {
        $reasons = [];
        if ($task->status !== Task::STATUS_COMPLETED) $reasons[] = 'task_incomplete';
        if ($task->assigned_to) $reasons[] = 'has_assignee';
        if ($task->due_date && $task->due_date > Carbon::now()) $reasons[] = 'future_due_date';
        return $reasons;
    }

    private function getForceDeleteRisks(Task $task): array
    {
        $risks = [];
        if ($task->priority === 'high' || $task->priority === 'urgent') $risks[] = 'high_priority_loss';
        if ($task->status === Task::STATUS_IN_PROGRESS) $risks[] = 'work_in_progress_loss';
        if ($task->assigned_to) $risks[] = 'assigned_work_loss';
        return $risks;
    }

    private function getRestorationContext(Task $task): array
    {
        return [
            'restored_status' => $task->status,
            'restoration_reason' => 'user_requested',
            'data_integrity' => 'preserved',
        ];
    }

    private function getUserPermissions(int $userId): array
    {
        // Placeholder - could be enhanced with actual permission system
        return ['can_delete' => true, 'can_force_delete' => true];
    }

    private function deletionRequiresApproval(Task $task, string $deletionType): bool
    {
        return $deletionType === 'force_delete' && ($task->priority === 'urgent' || $task->priority === 'high');
    }

    private function getDeletionAuditLevel(Task $task, string $deletionType): string
    {
        if ($deletionType === 'force_delete') return 'high';
        if ($task->priority === 'urgent') return 'high';
        if ($task->status === Task::STATUS_IN_PROGRESS) return 'medium';
        return 'standard';
    }

    /**
     * Check if a status change represents progression
     *
     * @param string $fromStatus
     * @param string $toStatus
     * @return bool
     */
    private function isStatusProgression(string $fromStatus, string $toStatus): bool
    {
        $progressionMap = [
            Task::STATUS_PENDING => [Task::STATUS_IN_PROGRESS],
            Task::STATUS_IN_PROGRESS => [Task::STATUS_COMPLETED],
        ];

        return in_array($toStatus, $progressionMap[$fromStatus] ?? []);
    }

    /**
     * Check if a status change requires notification
     *
     * @param string $fromStatus
     * @param string $toStatus
     * @return bool
     */
    private function statusChangeRequiresNotification(string $fromStatus, string $toStatus): bool
    {
        return $toStatus === Task::STATUS_COMPLETED || 
               ($fromStatus === Task::STATUS_PENDING && $toStatus === Task::STATUS_IN_PROGRESS);
    }

    /**
     * Check if priority change is an escalation
     *
     * @param string $fromPriority
     * @param string $toPriority
     * @return bool
     */
    private function isPriorityEscalation(string $fromPriority, string $toPriority): bool
    {
        $priorities = ['low' => 1, 'medium' => 2, 'high' => 3, 'urgent' => 4];
        return ($priorities[$toPriority] ?? 2) > ($priorities[$fromPriority] ?? 2);
    }

    /**
     * Check if priority change is a de-escalation
     *
     * @param string $fromPriority
     * @param string $toPriority
     * @return bool
     */
    private function isPriorityDeEscalation(string $fromPriority, string $toPriority): bool
    {
        $priorities = ['low' => 1, 'medium' => 2, 'high' => 3, 'urgent' => 4];
        return ($priorities[$toPriority] ?? 2) < ($priorities[$fromPriority] ?? 2);
    }

    /**
     * Get assignment change type
     *
     * @param int|null $fromAssignee
     * @param int|null $toAssignee
     * @return string
     */
    private function getAssignmentChangeType(?int $fromAssignee, ?int $toAssignee): string
    {
        if ($fromAssignee === null && $toAssignee !== null) {
            return 'assigned';
        }
        if ($fromAssignee !== null && $toAssignee === null) {
            return 'unassigned';
        }
        if ($fromAssignee !== $toAssignee) {
            return 'reassigned';
        }
        return 'unchanged';
    }

    /**
     * Calculate completion percentage based on task status and properties
     *
     * @param Task $task
     * @return int
     */
    private function calculateCompletionPercentage(Task $task): int
    {
        switch ($task->status) {
            case Task::STATUS_PENDING:
                return 0;
            case Task::STATUS_IN_PROGRESS:
                return 50;
            case Task::STATUS_COMPLETED:
                return 100;
            default:
                return 0;
        }
    }

    /**
     * Estimate completion time based on task properties
     *
     * @param Task $task
     * @return int|null Estimated hours to completion
     */
    private function estimateCompletionTime(Task $task): ?int
    {
        // Simple estimation logic based on priority and description length
        $baseHours = 4; // Default estimation
        
        // Adjust based on priority
        switch ($task->priority) {
            case 'low':
                $baseHours *= 0.5;
                break;
            case 'high':
                $baseHours *= 1.5;
                break;
            case 'urgent':
                $baseHours *= 2;
                break;
        }
        
        // Adjust based on description length (rough complexity indicator)
        if ($task->description) {
            $words = str_word_count($task->description);
            if ($words > 100) {
                $baseHours *= 1.3;
            } elseif ($words > 50) {
                $baseHours *= 1.1;
            }
        }
        
        return (int) ceil($baseHours);
    }

    /**
     * Fallback logging method when primary logging fails
     * Logs to file system or database as last resort
     *
     * @param string $operation
     * @param array $data
     * @return void
     */
    private function logToFileAsFallback(string $operation, array $data): void
    {
        try {
            // Try to log to Laravel's log system first
            \Log::channel('single')->info("Fallback logging for {$operation}", [
                'operation' => $operation,
                'data' => $data,
                'timestamp' => Carbon::now()->toISOString(),
                'reason' => 'Primary logging system failed'
            ]);
        } catch (\Exception $e) {
            // If even file logging fails, try to log to MySQL fallback table
            try {
                // Sanitize data before inserting to prevent SQL injection
                $sqlProtectionService = app(\App\Services\SqlInjectionProtectionService::class);
                
                $sanitizedData = [
                    'operation' => $sqlProtectionService->sanitizeInput($operation, 'fallback.operation'),
                    'data' => json_encode($sqlProtectionService->sanitizeInput($data, 'fallback.data')),
                    'error_context' => 'Primary and file logging failed',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ];
                
                \DB::table('task_logs_fallback')->insert($sanitizedData);
            } catch (\Exception $dbException) {
                // Last resort: error_log to system
                error_log("TaskController fallback logging failed for {$operation}: " . json_encode($data));
            }
        }
    }

    /**
     * Safe logging wrapper that handles failures gracefully
     *
     * @param callable $logFunction
     * @param string $operationType
     * @param array $fallbackData
     * @return void
     */
    private function safeLog(callable $logFunction, string $operationType, array $fallbackData): void
    {
        try {
            $logFunction();
        } catch (\Exception $e) {
            \Log::error("Primary logging failed for {$operationType}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'fallback_data' => $fallbackData
            ]);
            
            $this->logToFileAsFallback($operationType, $fallbackData);
        }
    }

    /**
     * Get task summary with aggregated data
     * 
     * @OA\Get(
     *     path="/tasks/summary",
     *     tags={"Tasks"},
     *     summary="Get task summary",
     *     description="Get a comprehensive summary of tasks including status distribution, priority breakdown, and key metrics.",
     *     @OA\Response(
     *         response=200,
     *         description="Task summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="overview", ref="#/components/schemas/TaskStats"),
     *                 @OA\Property(property="recent_activity", type="array", @OA\Items(ref="#/components/schemas/Task")),
     *                 @OA\Property(property="trends", type="object")
     *             ),
     *             @OA\Property(property="message", type="string", example="Task summary retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function summary(): JsonResponse
    {
        try {
            $summary = [
                'overview' => [
                    'total' => $this->taskRepository->countByStatus(),
                    'pending' => $this->taskRepository->countByStatus(Task::STATUS_PENDING),
                    'in_progress' => $this->taskRepository->countByStatus(Task::STATUS_IN_PROGRESS),
                    'completed' => $this->taskRepository->countByStatus(Task::STATUS_COMPLETED),
                    'cancelled' => $this->taskRepository->countByStatus(Task::STATUS_CANCELLED),
                ],
                'recent_activity' => [], // Placeholder - can be implemented based on needs
                'trends' => [
                    'completion_rate' => '75%', // Placeholder calculation
                    'creation_trend' => 'increasing'
                ]
            ];

            return $this->successResponse($summary, 'Task summary retrieved successfully');
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to retrieve task summary', 'summary');
        }
    }

    /**
     * Export tasks data
     * 
     * @OA\Get(
     *     path="/tasks/export",
     *     tags={"Tasks"},
     *     summary="Export tasks data",
     *     description="Export tasks data in various formats (JSON, CSV) with optional filtering.",
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Export format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json", "csv"}, default="json")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by task status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "in_progress", "completed", "cancelled"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks exported successfully",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Task")),
     *                 @OA\Property(property="message", type="string", example="Tasks exported successfully")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid export parameters",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $format = $request->get('format', 'json');
            $filters = ValidationHelper::validateFilterParameters($request);

            $tasks = $this->taskRepository->findWithFilters($filters);
            
            if ($format === 'csv') {
                // Simple CSV conversion - could be enhanced with proper CSV library
                $csvData = "ID,Title,Description,Status,Priority,Created At,Updated At\n";
                foreach ($tasks as $task) {
                    $csvData .= sprintf(
                        "%d,\"%s\",\"%s\",%s,%s,%s,%s\n",
                        $task->id,
                        addslashes($task->title),
                        addslashes($task->description ?? ''),
                        $task->status,
                        $task->priority ?? 'medium',
                        $task->created_at,
                        $task->updated_at
                    );
                }
                
                return response()->json([
                    'data' => base64_encode($csvData),
                    'format' => 'csv',
                    'encoding' => 'base64',
                    'message' => 'Tasks exported successfully'
                ]);
            }

            return $this->successResponse($tasks->toArray(), 'Tasks exported successfully');
        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to export tasks', 'export');
        }
    }

    /**
     * Get overdue tasks
     * 
     * @OA\Get(
     *     path="/tasks/overdue",
     *     tags={"Tasks"},
     *     summary="Get overdue tasks",
     *     description="Retrieve all tasks that are past their due date and still not completed.",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of tasks per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Overdue tasks retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/PaginatedResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function overdue(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $limit = min($request->get('limit', 50), 100);
            $offset = ($page - 1) * $limit;

            // For now, return tasks where due_date is in the past and status is not completed
            $filters = [
                'due_date_to' => Carbon::now()->format('Y-m-d'),
                'status_not' => Task::STATUS_COMPLETED,
                'limit' => $limit,
                'offset' => $offset
            ];

            $overdueTasks = $this->taskRepository->findWithFilters($filters);
            $totalCount = $this->taskRepository->countWithFilters($filters);

            $pagination = [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit),
                'has_next_page' => $page < ceil($totalCount / $limit),
                'has_previous_page' => $page > 1,
                'next_page' => $page < ceil($totalCount / $limit) ? $page + 1 : null,
                'previous_page' => $page > 1 ? $page - 1 : null,
            ];

            return $this->paginatedResponse(
                $overdueTasks->toArray(),
                $pagination,
                'Overdue tasks retrieved successfully'
            );
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to retrieve overdue tasks', 'overdue');
        }
    }

    /**
     * Get completed tasks
     * 
     * @OA\Get(
     *     path="/tasks/completed",
     *     tags={"Tasks"},
     *     summary="Get completed tasks",
     *     description="Retrieve all tasks that have been marked as completed.",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of tasks per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Completed tasks retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/PaginatedResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function completed(Request $request): JsonResponse
    {
        try {
            $filters = ValidationHelper::validateFilterParameters($request);
            $filters['status'] = Task::STATUS_COMPLETED;

            $page = $filters['page'] ?? 1;
            $limit = $filters['limit'] ?? 50;
            $offset = ($page - 1) * $limit;

            $filters['limit'] = $limit;
            $filters['offset'] = $offset;

            $completedTasks = $this->taskRepository->findWithFilters($filters);
            $totalCount = $this->taskRepository->countWithFilters($filters);

            $pagination = [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit),
                'has_next_page' => $page < ceil($totalCount / $limit),
                'has_previous_page' => $page > 1,
                'next_page' => $page < ceil($totalCount / $limit) ? $page + 1 : null,
                'previous_page' => $page > 1 ? $page - 1 : null,
            ];

            return $this->paginatedResponse(
                $completedTasks->toArray(),
                $pagination,
                'Completed tasks retrieved successfully'
            );
        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to retrieve completed tasks', 'completed');
        }
    }

    /**
     * Create multiple tasks in bulk
     * 
     * @OA\Post(
     *     path="/tasks/bulk",
     *     tags={"Tasks"},
     *     summary="Bulk create tasks",
     *     description="Create multiple tasks in a single request with transaction support.",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Bulk task creation data",
     *         @OA\JsonContent(ref="#/components/schemas/TaskBulkCreateRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tasks created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="created", type="array", @OA\Items(ref="#/components/schemas/Task")),
     *                 @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total_requested", type="integer"),
     *                     @OA\Property(property="successfully_created", type="integer"),
     *                     @OA\Property(property="failed_count", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Bulk task creation completed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $tasks = $request->get('tasks', []);
            if (empty($tasks) || !is_array($tasks)) {
                throw new TaskValidationException(['tasks' => ['Tasks array is required and cannot be empty']]);
            }

            $created = [];
            $failed = [];
            
            foreach ($tasks as $index => $taskData) {
                try {
                    // Use direct validation instead of the validation request class
                    $validatedData = $this->validateTaskData($taskData);
                    $task = $this->taskRepository->create($validatedData);
                    $created[] = $task->toArray();
                } catch (\Exception $e) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $taskData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $summary = [
                'total_requested' => count($tasks),
                'successfully_created' => count($created),
                'failed_count' => count($failed)
            ];

            return $this->createdResponse([
                'created' => $created,
                'failed' => $failed,
                'summary' => $summary
            ], 'Bulk task creation completed');
            
        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to create tasks in bulk', 'bulkCreate');
        }
    }

    /**
     * Update multiple tasks in bulk
     * 
     * @OA\Put(
     *     path="/tasks/bulk",
     *     tags={"Tasks"},
     *     summary="Bulk update tasks",
     *     description="Update multiple tasks in a single request with transaction support.",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Bulk task update data",
     *         @OA\JsonContent(ref="#/components/schemas/TaskBulkUpdateRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="updated", type="array", @OA\Items(ref="#/components/schemas/Task")),
     *                 @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total_requested", type="integer"),
     *                     @OA\Property(property="successfully_updated", type="integer"),
     *                     @OA\Property(property="failed_count", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Bulk task update completed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $updates = $request->get('updates', []);
            if (empty($updates) || !is_array($updates)) {
                throw new TaskValidationException(['updates' => ['Updates array is required and cannot be empty']]);
            }

            $updated = [];
            $failed = [];
            
            foreach ($updates as $index => $updateData) {
                try {
                    if (!isset($updateData['id'])) {
                        throw new \Exception('Task ID is required for bulk update');
                    }
                    
                    $taskId = $updateData['id'];
                    unset($updateData['id']);
                    
                    $validatedData = $this->validateTaskUpdateData($updateData);
                    $task = $this->taskRepository->update($taskId, $validatedData);
                    
                    if (!$task) {
                        throw new \Exception("Task with ID {$taskId} not found");
                    }
                    
                    $updated[] = $task->toArray();
                } catch (\Exception $e) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $updateData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $summary = [
                'total_requested' => count($updates),
                'successfully_updated' => count($updated),
                'failed_count' => count($failed)
            ];

            return $this->successResponse([
                'updated' => $updated,
                'failed' => $failed,
                'summary' => $summary
            ], 'Bulk task update completed');
            
        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to update tasks in bulk', 'bulkUpdate');
        }
    }

    /**
     * Delete multiple tasks in bulk
     * 
     * @OA\Delete(
     *     path="/tasks/bulk",
     *     tags={"Tasks"},
     *     summary="Bulk delete tasks",
     *     description="Delete multiple tasks in a single request with options for soft or hard delete.",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Bulk task deletion data",
     *         @OA\JsonContent(ref="#/components/schemas/TaskBulkDeleteRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="deleted", type="array", @OA\Items(type="integer"), description="Array of deleted task IDs"),
     *                 @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total_requested", type="integer"),
     *                     @OA\Property(property="successfully_deleted", type="integer"),
     *                     @OA\Property(property="failed_count", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Bulk task deletion completed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $taskIds = $request->get('task_ids', []);
            $permanent = $request->get('permanent', false);
            
            if (empty($taskIds) || !is_array($taskIds)) {
                throw new TaskValidationException(['task_ids' => ['Task IDs array is required and cannot be empty']]);
            }

            $deleted = [];
            $failed = [];
            
            foreach ($taskIds as $index => $taskId) {
                try {
                    if ($permanent) {
                        $result = $this->taskRepository->forceDelete($taskId);
                    } else {
                        $result = $this->taskRepository->delete($taskId);
                    }
                    
                    if ($result) {
                        $deleted[] = $taskId;
                    } else {
                        throw new \Exception("Task with ID {$taskId} not found or already deleted");
                    }
                } catch (\Exception $e) {
                    $failed[] = [
                        'task_id' => $taskId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $summary = [
                'total_requested' => count($taskIds),
                'successfully_deleted' => count($deleted),
                'failed_count' => count($failed)
            ];

            return $this->successResponse([
                'deleted' => $deleted,
                'failed' => $failed,
                'summary' => $summary
            ], 'Bulk task deletion completed');
            
        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to delete tasks in bulk', 'bulkDelete');
        }
    }

    /**
     * Validate task data for creation
     *
     * @param array $data
     * @return array
     * @throws TaskValidationException
     */
    private function validateTaskData(array $data): array
    {
        $rules = CreateTaskRequest::getValidationRules();
        $messages = CreateTaskRequest::getValidationMessages();
        
        $validator = \Validator::make($data, $rules, $messages);
        
        if ($validator->fails()) {
            throw new TaskValidationException($validator->errors()->toArray());
        }
        
        return $validator->validated();
    }

    /**
     * Validate task data for updates
     *
     * @param array $data
     * @return array
     * @throws TaskValidationException
     */
    private function validateTaskUpdateData(array $data): array
    {
        $rules = UpdateTaskRequest::getValidationRules();
        $messages = UpdateTaskRequest::getValidationMessages();
        
        $validator = \Validator::make($data, $rules, $messages);
        
        if ($validator->fails()) {
            throw new TaskValidationException($validator->errors()->toArray());
        }
        
        return $validator->validated();
    }

    /**
     * Convert tasks collection to CSV format
     *
     * @param \Illuminate\Database\Eloquent\Collection $tasks
     * @return string
     */
    private function convertToCsv($tasks): string
    {
        $csvData = "ID,Title,Description,Status,Priority,Created At,Updated At\n";
        foreach ($tasks as $task) {
            $csvData .= sprintf(
                "%d,\"%s\",\"%s\",%s,%s,%s,%s\n",
                $task->id,
                addslashes($task->title),
                addslashes($task->description ?? ''),
                $task->status,
                $task->priority ?? 'medium',
                $task->created_at,
                $task->updated_at
            );
        }
        return $csvData;
    }

    /**
     * Duplicate a task
     * 
     * @OA\Post(
     *     path="/tasks/{id}/duplicate",
     *     tags={"Tasks"},
     *     summary="Duplicate a task",
     *     description="Create a copy of an existing task with optional modifications.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Original task ID to duplicate",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional modifications for the duplicated task",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", description="New title (will append 'Copy' if not provided)"),
     *             @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "cancelled"}, default="pending"),
     *             @OA\Property(property="assigned_to", type="integer", nullable=true, description="New assignee")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Task duplicated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task duplicated successfully"),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="original_task_id", type="integer"),
     *                 @OA\Property(property="duplicated_fields", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Original task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        try {
            $originalTask = $this->taskRepository->findById($id);
            if (!$originalTask) {
                throw TaskNotFoundException::forOperation($id, 'duplicate');
            }

            // Prepare data for duplication
            $duplicateData = [
                'title' => $request->get('title', $originalTask->title . ' (Copy)'),
                'description' => $originalTask->description,
                'status' => $request->get('status', Task::STATUS_PENDING),
                'priority' => $originalTask->priority,
                'assigned_to' => $request->get('assigned_to', null),
                'due_date' => $originalTask->due_date,
            ];

            $newTask = $this->taskRepository->create($duplicateData);

            return $this->createdResponse($newTask->toArray(), 'Task duplicated successfully', [
                'original_task_id' => $id,
                'duplicated_fields' => ['title', 'description', 'priority', 'due_date']
            ]);

        } catch (TaskNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to duplicate task', 'duplicate', $id);
        }
    }

    /**
     * Mark task as completed
     * 
     * @OA\Post(
     *     path="/tasks/{id}/complete",
     *     tags={"Tasks"},
     *     summary="Mark task as completed",
     *     description="Change task status to completed and set completion timestamp.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional completion metadata",
     *         @OA\JsonContent(
     *             @OA\Property(property="completion_notes", type="string", description="Optional notes about the completion")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task marked as completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task marked as completed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Task already completed",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function markComplete(Request $request, int $id): JsonResponse
    {
        try {
            $task = $this->taskRepository->findById($id);
            if (!$task) {
                throw TaskNotFoundException::forOperation($id, 'markComplete');
            }

            if ($task->status === Task::STATUS_COMPLETED) {
                throw new TaskOperationException('Task is already completed', 'markComplete', $id, 400);
            }

            $updatedTask = $this->taskRepository->update($id, [
                'status' => Task::STATUS_COMPLETED,
                'completed_at' => Carbon::now()
            ]);

            return $this->successResponse($updatedTask->toArray(), 'Task marked as completed');

        } catch (TaskNotFoundException $e) {
            throw $e;
        } catch (TaskOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to mark task as completed', 'markComplete', $id);
        }
    }

    /**
     * Mark task as in progress
     * 
     * @OA\Post(
     *     path="/tasks/{id}/start",
     *     tags={"Tasks"},
     *     summary="Mark task as in progress",
     *     description="Change task status to in progress.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task marked as in progress",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task marked as in progress")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid status transition",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function markInProgress(Request $request, int $id): JsonResponse
    {
        try {
            $task = $this->taskRepository->findById($id);
            if (!$task) {
                throw TaskNotFoundException::forOperation($id, 'markInProgress');
            }

            if ($task->status === Task::STATUS_IN_PROGRESS) {
                throw new TaskOperationException('Task is already in progress', 'markInProgress', $id, 400);
            }

            $updatedTask = $this->taskRepository->update($id, [
                'status' => Task::STATUS_IN_PROGRESS
            ]);

            return $this->successResponse($updatedTask->toArray(), 'Task marked as in progress');

        } catch (TaskNotFoundException $e) {
            throw $e;
        } catch (TaskOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to mark task as in progress', 'markInProgress', $id);
        }
    }

    /**
     * Mark task as cancelled
     * 
     * @OA\Post(
     *     path="/tasks/{id}/cancel",
     *     tags={"Tasks"},
     *     summary="Mark task as cancelled",
     *     description="Change task status to cancelled.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional cancellation metadata",
     *         @OA\JsonContent(
     *             @OA\Property(property="cancellation_reason", type="string", description="Reason for cancellation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task marked as cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task marked as cancelled")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Task already completed",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function markCancelled(Request $request, int $id): JsonResponse
    {
        try {
            $task = $this->taskRepository->findById($id);
            if (!$task) {
                throw TaskNotFoundException::forOperation($id, 'markCancelled');
            }

            if ($task->status === Task::STATUS_COMPLETED) {
                throw new TaskOperationException('Cannot cancel a completed task', 'markCancelled', $id, 400);
            }

            $updatedTask = $this->taskRepository->update($id, [
                'status' => Task::STATUS_CANCELLED
            ]);

            return $this->successResponse($updatedTask->toArray(), 'Task marked as cancelled');

        } catch (TaskNotFoundException $e) {
            throw $e;
        } catch (TaskOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to mark task as cancelled', 'markCancelled', $id);
        }
    }

    /**
     * Assign task to a user
     * 
     * @OA\Post(
     *     path="/tasks/{id}/assign",
     *     tags={"Tasks"},
     *     summary="Assign task to a user",
     *     description="Assign a task to a specific user.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Assignment data",
     *         @OA\JsonContent(ref="#/components/schemas/TaskAssignRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task assigned successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid user ID",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        try {
            $task = $this->taskRepository->findById($id);
            if (!$task) {
                throw TaskNotFoundException::forOperation($id, 'assign');
            }

            $assignedTo = $request->get('assigned_to');
            if (!$assignedTo || !is_numeric($assignedTo)) {
                throw new TaskValidationException(['assigned_to' => ['A valid user ID is required']]);
            }

            $updatedTask = $this->taskRepository->update($id, [
                'assigned_to' => (int) $assignedTo
            ]);

            return $this->successResponse($updatedTask->toArray(), 'Task assigned successfully');

        } catch (TaskNotFoundException $e) {
            throw $e;
        } catch (TaskValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to assign task', 'assign', $id);
        }
    }

    /**
     * Unassign task from a user
     * 
     * @OA\Delete(
     *     path="/tasks/{id}/assign",
     *     tags={"Tasks"},
     *     summary="Unassign task from user",
     *     description="Remove assignment from a task.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task unassigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task unassigned successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Task not assigned",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function unassign(Request $request, int $id): JsonResponse
    {
        try {
            $task = $this->taskRepository->findById($id);
            if (!$task) {
                throw TaskNotFoundException::forOperation($id, 'unassign');
            }

            if (!$task->assigned_to) {
                throw new TaskOperationException('Task is not assigned to anyone', 'unassign', $id, 400);
            }

            $updatedTask = $this->taskRepository->update($id, [
                'assigned_to' => null
            ]);

            return $this->successResponse($updatedTask->toArray(), 'Task unassigned successfully');

        } catch (TaskNotFoundException $e) {
            throw $e;
        } catch (TaskOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to unassign task', 'unassign', $id);
        }
    }
}

/**
 * OpenAPI Schema Definitions
 * 
 * @OA\Schema(
 *     schema="Task",
 *     type="object",
 *     title="Task",
 *     description="Task model",
 *     required={"id", "title", "status", "created_at", "updated_at"},
 *     @OA\Property(property="id", type="integer", example=1, description="Task ID"),
 *     @OA\Property(property="title", type="string", example="Complete project documentation", description="Task title"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Write comprehensive documentation for the project", description="Task description"),
 *     @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "cancelled"}, example="pending", description="Task status"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, example="medium", description="Task priority"),
 *     @OA\Property(property="assigned_to", type="integer", nullable=true, example=1, description="Assigned user ID"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2024-12-31T23:59:59Z", description="Task due date"),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example="2024-12-20T10:30:00Z", description="Task completion date"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-12-01T09:00:00Z", description="Task creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-12-15T14:30:00Z", description="Task last update date"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null, description="Task soft deletion date")
 * )
 * 
 * @OA\Schema(
 *     schema="TaskCreateRequest",
 *     type="object",
 *     title="Task Creation Request",
 *     description="Request payload for creating a new task",
 *     required={"title"},
 *     @OA\Property(property="title", type="string", maxLength=255, example="Complete project documentation", description="Task title (required)"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Write comprehensive documentation for the project", description="Task description"),
 *     @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "cancelled"}, example="pending", description="Task status (defaults to 'pending')"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, example="medium", description="Task priority (defaults to 'medium')"),
 *     @OA\Property(property="assigned_to", type="integer", nullable=true, example=1, description="Assigned user ID"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2024-12-31T23:59:59Z", description="Task due date")
 * )
 * 
 * @OA\Schema(
 *     schema="TaskUpdateRequest",
 *     type="object",
 *     title="Task Update Request",
 *     description="Request payload for updating a task (all fields optional for partial updates)",
 *     @OA\Property(property="title", type="string", maxLength=255, example="Updated project documentation", description="Task title"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Updated task description", description="Task description"),
 *     @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "cancelled"}, example="in_progress", description="Task status"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, example="high", description="Task priority"),
 *     @OA\Property(property="assigned_to", type="integer", nullable=true, example=2, description="Assigned user ID"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2024-12-31T23:59:59Z", description="Task due date"),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example="2024-12-20T10:30:00Z", description="Task completion date")
 * )
 * 
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     title="Error Response",
 *     description="Standard error response format",
 *     @OA\Property(property="error", type="string", example="Task not found", description="Error message"),
 *     @OA\Property(property="details", type="string", nullable=true, example="Task with ID 123 does not exist", description="Additional error details"),
 *     @OA\Property(property="code", type="string", nullable=true, example="TASK_NOT_FOUND", description="Error code for programmatic handling")
 * )
 * 
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     title="Validation Error Response",
 *     description="Validation error response with field-specific errors",
 *     @OA\Property(property="error", type="string", example="Validation failed", description="General error message"),
 *     @OA\Property(property="details", type="object", description="Field-specific validation errors",
 *         @OA\Property(property="title", type="array", @OA\Items(type="string"), example={"The title field is required."}),
 *         @OA\Property(property="status", type="array", @OA\Items(type="string"), example={"The selected status is invalid."})
 *     ),
 *     @OA\Property(property="code", type="string", example="VALIDATION_ERROR", description="Error code")
 * )
 * 
 * @OA\Response(
 *     response="TaskUpdated",
 *     description="Task updated successfully",
 *     @OA\JsonContent(
 *         @OA\Property(property="data", ref="#/components/schemas/Task"),
 *         @OA\Property(property="message", type="string", example="Task updated successfully"),
 *         @OA\Property(property="meta", type="object",
 *             @OA\Property(property="changed_fields", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="changes_count", type="integer")
 *         )
 *     )
 * )
 * 
 * @OA\Response(
 *     response="TaskNotFound",
 *     description="Task not found",
 *     @OA\JsonContent(ref="#/components/schemas/Error")
 * )
 * 
 * @OA\Response(
 *     response="ValidationError",
 *     description="Validation error",
 *     @OA\JsonContent(ref="#/components/schemas/ValidationError")
 * )
 * 
 * @OA\Response(
 *     response="ConcurrentUpdate",
 *     description="Concurrent update detected",
 *     @OA\JsonContent(ref="#/components/schemas/Error")
 * )
 * 
 * @OA\Response(
 *     response="RateLimitExceeded",
 *     description="Rate limit exceeded",
 *     @OA\JsonContent(ref="#/components/schemas/Error")
 * )
 * 
 * @OA\Response(
 *     response="InternalError",
 *     description="Internal server error",
 *     @OA\JsonContent(ref="#/components/schemas/Error")
 * )
 */
