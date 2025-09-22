<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\Task;

class TaskApiIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testGetAllTasksEndpoint()
    {
        // Create test tasks
        Task::factory()->count(3)->create([
            'status' => 'pending'
        ]);

        $this->get('/api/v1/tasks')
            ->assertResponseStatus(200)
            ->assertApiSuccess($this->response);

        $responseData = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
    }

    public function testGetAllTasksWithFilteringAndPagination()
    {
        // Create tasks with different statuses
        Task::factory()->count(5)->create(['status' => 'pending']);
        Task::factory()->count(3)->create(['status' => 'completed']);

        $this->get('/api/v1/tasks?status=pending&page=1&per_page=3')
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
        $this->assertLessThanOrEqual(3, count($responseData['data']));
    }

    public function testCreateTaskEndpoint()
    {
        $taskData = [
            'title' => 'Integration Test Task',
            'description' => 'This is a test task created during integration testing',
            'status' => 'pending',
            'priority' => 'high',
            'assigned_to' => 'test@example.com',
            'due_date' => '2024-12-31'
        ];

        $this->json('POST', '/api/v1/tasks', $taskData)
            ->assertResponseStatus(201);

        $this->assertApiSuccess($this->response, 'Task created successfully');
        
        $responseData = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals($taskData['title'], $responseData['data']['title']);
    }

    public function testCreateTaskWithValidationErrors()
    {
        $invalidTaskData = [
            'title' => '', // Empty title should fail validation
            'description' => 'Test description',
            'status' => 'invalid_status', // Invalid status
            'priority' => 'super_urgent' // Invalid priority
        ];

        $this->json('POST', '/api/v1/tasks', $invalidTaskData)
            ->assertResponseStatus(422);

        $this->assertApiError($this->response, null, 422);
        
        $responseData = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('errors', $responseData);
    }

    public function testGetSingleTaskEndpoint()
    {
        $task = Task::factory()->create([
            'title' => 'Test Task for Retrieval'
        ]);

        $this->get("/api/v1/tasks/{$task->id}")
            ->assertResponseStatus(200);

        $this->assertApiSuccess($this->response);
        
        $responseData = json_decode($this->response->getContent(), true);
        $this->assertEquals($task->title, $responseData['data']['title']);
        $this->assertEquals($task->id, $responseData['data']['id']);
    }

    public function testGetNonExistentTask()
    {
        $nonExistentId = 999999;

        $this->get("/api/v1/tasks/{$nonExistentId}")
            ->assertResponseStatus(404);

        $this->assertApiError($this->response, 'Task not found', 404);
    }

    public function testUpdateTaskEndpoint()
    {
        $task = Task::factory()->create([
            'title' => 'Original Title',
            'status' => 'pending'
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'status' => 'in_progress'
        ];

        $this->json('PUT', "/api/v1/tasks/{$task->id}", $updateData)
            ->assertResponseStatus(200);

        $this->assertApiSuccess($this->response, 'Task updated successfully');
        
        $responseData = json_decode($this->response->getContent(), true);
        $this->assertEquals('Updated Title', $responseData['data']['title']);
        $this->assertEquals('in_progress', $responseData['data']['status']);
    }

    public function testUpdateTaskWithValidationErrors()
    {
        $task = Task::factory()->create();
        
        $invalidUpdateData = [
            'title' => str_repeat('a', 256), // Title too long
            'status' => 'invalid_status',
            'priority' => 'invalid_priority'
        ];

        $this->json('PUT', "/api/v1/tasks/{$task->id}", $invalidUpdateData)
            ->assertResponseStatus(422);

        $this->assertApiError($this->response, null, 422);
    }

    public function testSoftDeleteTaskEndpoint()
    {
        $task = Task::factory()->create();

        $this->json('DELETE', "/api/v1/tasks/{$task->id}")
            ->assertResponseStatus(200);

        $this->assertApiSuccess($this->response, 'Task deleted successfully');
        
        // Verify task is soft deleted
        $deletedTask = Task::withTrashed()->find($task->id);
        $this->assertNotNull($deletedTask);
        $this->assertNotNull($deletedTask->deleted_at);
    }

    public function testRestoreTaskEndpoint()
    {
        $task = Task::factory()->create();
        $task->delete(); // Soft delete

        $this->json('POST', "/api/v1/tasks/{$task->id}/restore")
            ->assertResponseStatus(200);

        $this->assertApiSuccess($this->response, 'Task restored successfully');
        
        // Verify task is restored
        $restoredTask = Task::find($task->id);
        $this->assertNotNull($restoredTask);
        $this->assertNull($restoredTask->deleted_at);
    }

    public function testTaskSearchFunctionality()
    {
        // Create tasks with searchable content
        Task::factory()->create([
            'title' => 'Important Project Task',
            'description' => 'This task is related to the main project'
        ]);
        
        Task::factory()->create([
            'title' => 'Regular Task',
            'description' => 'This is a regular daily task'
        ]);

        $this->get('/api/v1/tasks?search=important')
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        
        // Should return only tasks containing "important"
        foreach ($responseData['data'] as $task) {
            $this->assertTrue(
                stripos($task['title'], 'important') !== false ||
                stripos($task['description'], 'important') !== false
            );
        }
    }

    public function testTaskPriorityFiltering()
    {
        // Create tasks with different priorities
        Task::factory()->count(2)->create(['priority' => 'high']);
        Task::factory()->count(3)->create(['priority' => 'medium']);
        Task::factory()->count(1)->create(['priority' => 'low']);

        $this->get('/api/v1/tasks?priority=high')
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        
        foreach ($responseData['data'] as $task) {
            $this->assertEquals('high', $task['priority']);
        }
    }

    public function testTaskDateRangeFiltering()
    {
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';

        // Create tasks within and outside the date range
        Task::factory()->create(['due_date' => '2024-06-15']);
        Task::factory()->create(['due_date' => '2025-01-15']); // Outside range

        $this->get("/api/v1/tasks?due_date_from={$startDate}&due_date_to={$endDate}")
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        
        foreach ($responseData['data'] as $task) {
            $this->assertGreaterThanOrEqual($startDate, $task['due_date']);
            $this->assertLessThanOrEqual($endDate, $task['due_date']);
        }
    }

    public function testBulkTaskOperations()
    {
        $tasks = Task::factory()->count(3)->create(['status' => 'pending']);
        $taskIds = $tasks->pluck('id')->toArray();

        $bulkUpdateData = [
            'task_ids' => $taskIds,
            'status' => 'in_progress'
        ];

        $this->json('PUT', '/api/v1/tasks/bulk-update', $bulkUpdateData)
            ->assertResponseStatus(200);

        $this->assertApiSuccess($this->response, 'Tasks updated successfully');
        
        // Verify all tasks were updated
        foreach ($taskIds as $taskId) {
            $task = Task::find($taskId);
            $this->assertEquals('in_progress', $task->status);
        }
    }
}