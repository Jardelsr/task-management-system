<?php

namespace App\Http\Controllers;

use App\Repositories\TaskRepositoryInterface;
use App\Services\LogServiceInterface;
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

            $validatedData = $validator->validated();
            $task = $this->taskRepository->create($validatedData);
            
            // Enhanced logging for task creation
            try {
                $this->logTaskCreation($task, $validatedData, $request);
            } catch (\Exception $e) {
                // Log the logging error but don't fail the creation
                \Log::warning('Failed to create comprehensive task creation log', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
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

            // Enhanced comprehensive logging for task update
            try {
                $this->logTaskUpdate(
                    $task, 
                    $updatedTask, 
                    $inputData, 
                    $validator->validated(), 
                    $changedFields, 
                    $request
                );
            } catch (\Exception $e) {
                // Log the logging error but don't fail the update
                \Log::warning('Failed to create comprehensive task update log', [
                    'task_id' => $updatedTask->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
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
            
            // Get task info before deletion for metadata and logging
            $task = $this->taskRepository->findById($validatedId);
            $originalData = $task->toArray();
            
            // Delete the task - repository will throw TaskNotFoundException if not found
            $deleteResult = $this->taskRepository->delete($validatedId);
            
            // Log the task deletion comprehensively
            $this->logTaskDeletion($task, 'soft_delete', $request, [
                'deletion_reason' => $request->input('reason', 'user_initiated'),
                'batch_operation' => $request->header('X-Batch-ID') ? true : false,
            ]);
            
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
            $this->logTaskDeletion($restoredTask, 'restore', $request, [
                'restoration_reason' => $request->input('reason', 'user_requested'),
                'previous_state' => 'trashed',
                'restored_at' => Carbon::now()->toISOString(),
            ]);

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
            $this->logTaskDeletion($task, 'force_delete', $request, [
                'deletion_reason' => $request->input('reason', 'permanent_cleanup'),
                'confirmation_token' => $request->header('X-Confirmation-Token'),
                'was_trashed_first' => $task->deleted_at !== null,
                'final_deletion_at' => Carbon::now()->toISOString(),
            ]);
            
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
        $this->logService->createTaskActivityLog(
            $task->id,
            TaskLog::ACTION_CREATED,
            [], // No old data for creation
            $task->toArray(),
            $userId
        );

        // Create a detailed creation log entry
        $this->logService->createLog(
            $task->id,
            'task_creation_details',
            $logData,
            $userId,
            $this->generateCreationDescription($task, $validatedData)
        );

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
        $this->logService->createTaskActivityLog(
            $updatedTask->id,
            TaskLog::ACTION_UPDATED,
            array_intersect_key($originalTask->toArray(), array_flip($changedFields)),
            array_intersect_key($updatedTask->toArray(), array_flip($changedFields)),
            $userId
        );

        // Create detailed update log entry
        $this->logService->createLog(
            $updatedTask->id,
            'task_update_details',
            $logData,
            $userId,
            $this->generateUpdateDescription($originalTask, $updatedTask, $changedFields)
        );

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
}
