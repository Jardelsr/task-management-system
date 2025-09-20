<?php

namespace App\Http\Controllers;

use App\Repositories\TaskRepositoryInterface;
use App\Repositories\LogRepositoryInterface;
use App\Exceptions\TaskNotFoundException;
use App\Exceptions\TaskValidationException;
use App\Exceptions\TaskOperationException;
use App\Http\Requests\CreateTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
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
            $tasks = $this->taskRepository->findAll();
            return $this->successResponse($tasks, 'Tasks retrieved successfully');
        } catch (\Exception $e) {
            throw new TaskOperationException('Failed to retrieve tasks', 'index');
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $task = $this->taskRepository->findById($id);

            if (!$task) {
                throw new TaskNotFoundException($id);
            }

            return $this->successResponse($task, 'Task retrieved successfully');
        } catch (TaskNotFoundException $e) {
            throw $e;
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
            $task = $this->taskRepository->findById($id);
            
            if (!$task) {
                throw new TaskNotFoundException($id);
            }

            $validator = app('validator')->make(
                $request->all(), 
                UpdateTaskRequest::getValidationRules(),
                UpdateTaskRequest::getValidationMessages()
            );

            if ($validator->fails()) {
                throw new TaskValidationException($validator->errors()->toArray());
            }

            $updatedTask = $this->taskRepository->update($id, $validator->validated());
            return $this->taskOperationResponse($updatedTask->toArray(), 'updated');
        } catch (TaskNotFoundException | TaskValidationException $e) {
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $task = $this->taskRepository->findById($id);
            
            if (!$task) {
                throw new TaskNotFoundException($id);
            }

            $this->taskRepository->delete($id);
            return $this->deletedResponse('Task deleted successfully');
        } catch (TaskNotFoundException $e) {
            throw $e;
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
}