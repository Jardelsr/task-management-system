<?php

namespace App\Http\Controllers;

use App\Repositories\TaskRepositoryInterface;
use App\Repositories\LogRepositoryInterface;
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

class TaskController extends Controller
{
    protected TaskRepositoryInterface $taskRepository;
    protected LogRepositoryInterface $logRepository;

    public function __construct(
        TaskRepositoryInterface $taskRepository,
        LogRepositoryInterface $logRepository
    ) {
        $this->taskRepository = $taskRepository;
        $this->logRepository = $logRepository;
    }

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

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = app('validator')->make(
                $request->all(), 
                CreateTaskRequest::getValidationRules(),
                CreateTaskRequest::getValidationMessages()
            );

            if ($validator->fails()) {
                throw new TaskValidationException($validator->errors()->toArray());
            }

            $task = $this->taskRepository->create($validator->validated());
            return $this->createdResponse($task->toArray(), 'Task created successfully');
        } catch (TaskValidationException $e) {
            throw $e;
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            // Find the existing task - repository will throw TaskNotFoundException if not found
            $task = $this->taskRepository->findById($validatedId);

            // Validate and prepare update data with comprehensive validation
            $inputData = $request->all();
            $validatedData = ValidationHelper::validateAndPrepareUpdateData($inputData, $task);

            // Skip update if no valid data provided
            if (empty($validatedData)) {
                return $this->successResponse(
                    $task->toArray(), 
                    'No valid data provided for update'
                );
            }

            // Additional Laravel validation as backup
            $validator = app('validator')->make(
                $validatedData, 
                UpdateTaskRequest::getPartialUpdateRules($validatedData),
                UpdateTaskRequest::getValidationMessages()
            );

            if ($validator->fails()) {
                throw new TaskValidationException($validator->errors()->toArray());
            }

            // Store original data for comparison
            $originalData = $task->toArray();

            // Perform the partial update - repository will throw TaskNotFoundException if task doesn't exist
            $updatedTask = $this->taskRepository->update($validatedId, $validator->validated());

            // Determine what fields were actually changed
            $changedFields = $this->getChangedFields($originalData, $updatedTask->toArray());

            // Log the update operation
            try {
                $this->logRepository->create([
                    'task_id' => $updatedTask->id,
                    'action' => 'updated',
                    'changes' => [
                        'before' => array_intersect_key($originalData, array_flip($changedFields)),
                        'after' => array_intersect_key($updatedTask->toArray(), array_flip($changedFields)),
                        'changed_fields' => $changedFields
                    ],
                    'user_id' => $request->header('X-User-Id', 1), // Default to 1 if no user header
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Exception $e) {
                // Log the logging error but don't fail the update
                \Log::warning('Failed to create update log', [
                    'task_id' => $updatedTask->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Create appropriate response based on changes
            $message = empty($changedFields) 
                ? 'Task update requested but no changes were made'
                : 'Task updated successfully. Changed fields: ' . implode(', ', $changedFields);

            $response = $this->updatedResponse($updatedTask->toArray(), $message);
            
            // Add validation metadata to response
            $response->header('X-Validation-Version', '2.0');
            $response->header('X-Changed-Fields', implode(',', $changedFields));
            
            return $response;
            
        } catch (TaskNotFoundException | TaskValidationException | TaskOperationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TaskOperationException(
                'Unexpected error during task update: ' . $e->getMessage(),
                'update',
                $id
            );
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            // Get task info before deletion for metadata
            $task = $this->taskRepository->findById($validatedId);
            
            // Delete the task - repository will throw TaskNotFoundException if not found
            $deleteResult = $this->taskRepository->delete($validatedId);
            
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
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
            // Restore the task - repository will handle task existence and restore validation
            $restoreResult = $this->taskRepository->restore($validatedId);
            
            // Return restored task data
            $restoredTask = $this->taskRepository->findById($validatedId);

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
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        try {
            // Validate task ID format
            $validatedId = ValidationHelper::validateTaskId($id);
            
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
     * @param Request $request
     * @return JsonResponse
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
}