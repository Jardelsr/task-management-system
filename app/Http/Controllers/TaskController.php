<?php

namespace App\Http\Controllers;

use App\Repositories\TaskRepositoryInterface;
use App\Repositories\LogRepositoryInterface;
use App\Exceptions\TaskNotFoundException;
use App\Exceptions\TaskValidationException;
use App\Exceptions\TaskOperationException;
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
            
            // Find the existing task
            $task = $this->taskRepository->findById($validatedId);
            
            if (!$task) {
                throw TaskNotFoundException::forOperation($validatedId, 'update', [
                    'requested_data' => $request->all()
                ]);
            }

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

            // Perform the partial update
            $updatedTask = $this->taskRepository->update($validatedId, $validator->validated());
            
            if (!$updatedTask) {
                throw new TaskOperationException(
                    'Failed to update task - task may have been deleted during the operation', 
                    'update', 
                    $validatedId
                );
            }

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
            
            $task = $this->taskRepository->findById($validatedId);
            
            if (!$task) {
                throw TaskNotFoundException::forOperation($validatedId, 'delete');
            }

            $deleteResult = $this->taskRepository->delete($validatedId);
            
            if (!$deleteResult) {
                throw new TaskOperationException(
                    'Failed to delete task - task may have been already deleted',
                    'delete',
                    $validatedId
                );
            }

            return $this->deletedResponse('Task deleted successfully');
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