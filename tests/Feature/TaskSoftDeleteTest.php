<?php

namespace Tests\Feature;

use Laravel\Lumen\Testing\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use App\Models\Task;
use App\Models\TaskLog;

class TaskSoftDeleteTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__.'/../../bootstrap/app.php';
    }

    /**
     * Test soft delete functionality
     */
    public function testSoftDeleteTask()
    {
        // Create a task
        $taskData = [
            'title' => 'Test Task for Soft Delete',
            'description' => 'This task will be soft deleted',
            'status' => 'pending',
            'created_by' => 1,
        ];

        $response = $this->post('/tasks', $taskData);
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $taskId = $responseData['data']['id'];

        // Soft delete the task
        $response = $this->delete("/tasks/{$taskId}");
        $response->assertResponseOk();

        // Verify task is soft deleted (not in regular queries)
        $response = $this->get("/tasks/{$taskId}");
        $response->assertResponseStatus(404);

        // Verify task exists in trashed tasks
        $response = $this->get('/tasks/trashed');
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $this->assertGreaterThan(0, $responseData['meta']['count']);
        
        $trashedTask = collect($responseData['data'])->firstWhere('id', $taskId);
        $this->assertNotNull($trashedTask);
        $this->assertNotNull($trashedTask['deleted_at']);
    }

    /**
     * Test restore functionality
     */
    public function testRestoreTask()
    {
        // Create and soft delete a task
        $taskData = [
            'title' => 'Test Task for Restore',
            'description' => 'This task will be restored',
            'status' => 'pending',
            'created_by' => 1,
        ];

        $response = $this->post('/tasks', $taskData);
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $taskId = $responseData['data']['id'];

        // Soft delete the task
        $this->delete("/tasks/{$taskId}");

        // Restore the task
        $response = $this->post("/tasks/{$taskId}/restore");
        $response->assertResponseOk();

        // Verify task is restored and accessible
        $response = $this->get("/tasks/{$taskId}");
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $this->assertEquals($taskId, $responseData['data']['id']);
        $this->assertNull($responseData['data']['deleted_at']);
    }

    /**
     * Test force delete functionality
     */
    public function testForceDeleteTask()
    {
        // Create a task
        $taskData = [
            'title' => 'Test Task for Force Delete',
            'description' => 'This task will be permanently deleted',
            'status' => 'pending',
            'created_by' => 1,
        ];

        $response = $this->post('/tasks', $taskData);
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $taskId = $responseData['data']['id'];

        // Force delete the task
        $response = $this->delete("/tasks/{$taskId}/force");
        $response->assertResponseOk();

        // Verify task is completely gone
        $response = $this->get("/tasks/{$taskId}");
        $response->assertResponseStatus(404);

        // Verify task is not in trashed tasks
        $response = $this->get('/tasks/trashed');
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $trashedTask = collect($responseData['data'])->firstWhere('id', $taskId);
        $this->assertNull($trashedTask);
    }

    /**
     * Test force delete on soft-deleted task
     */
    public function testForceDeleteSoftDeletedTask()
    {
        // Create and soft delete a task
        $taskData = [
            'title' => 'Test Task for Force Delete After Soft Delete',
            'description' => 'This task will be soft deleted then force deleted',
            'status' => 'pending',
            'created_by' => 1,
        ];

        $response = $this->post('/tasks', $taskData);
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $taskId = $responseData['data']['id'];

        // Soft delete first
        $this->delete("/tasks/{$taskId}");

        // Then force delete
        $response = $this->delete("/tasks/{$taskId}/force");
        $response->assertResponseOk();

        // Verify task is completely gone
        $response = $this->get("/tasks/{$taskId}");
        $response->assertResponseStatus(404);

        // Verify task is not in trashed tasks
        $response = $this->get('/tasks/trashed');
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $trashedTask = collect($responseData['data'])->firstWhere('id', $taskId);
        $this->assertNull($trashedTask);
    }

    /**
     * Test restore non-existent task
     */
    public function testRestoreNonExistentTask()
    {
        $response = $this->post('/tasks/99999/restore');
        $response->assertResponseStatus(409); // TaskRestoreException
        
        $responseData = json_decode($response->response->getContent(), true);
        $this->assertArrayHasKey('reason', $responseData);
        $this->assertEquals('not_found', $responseData['reason']);
    }

    /**
     * Test restore already active task
     */
    public function testRestoreActiveTask()
    {
        // Create a task
        $taskData = [
            'title' => 'Active Task',
            'description' => 'This task is already active',
            'status' => 'pending',
            'created_by' => 1,
        ];

        $response = $this->post('/tasks', $taskData);
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $taskId = $responseData['data']['id'];

        // Try to restore active task
        $response = $this->post("/tasks/{$taskId}/restore");
        $response->assertResponseStatus(409); // TaskRestoreException
        
        $responseData = json_decode($response->response->getContent(), true);
        $this->assertArrayHasKey('reason', $responseData);
        $this->assertEquals('already_restored', $responseData['reason']);
    }

    /**
     * Test force delete non-existent task
     */
    public function testForceDeleteNonExistentTask()
    {
        $response = $this->delete('/tasks/99999/force');
        $response->assertResponseStatus(404); // TaskNotFoundException
    }

    /**
     * Test trashed tasks listing
     */
    public function testTrashedTasksListing()
    {
        // Create and soft delete multiple tasks
        $taskIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $taskData = [
                'title' => "Trashed Task {$i}",
                'description' => "Task {$i} to be trashed",
                'status' => 'pending',
                'created_by' => 1,
            ];

            $response = $this->post('/tasks', $taskData);
            $responseData = json_decode($response->response->getContent(), true);
            $taskIds[] = $responseData['data']['id'];
        }

        // Soft delete all tasks
        foreach ($taskIds as $taskId) {
            $this->delete("/tasks/{$taskId}");
        }

        // Get trashed tasks
        $response = $this->get('/tasks/trashed');
        $response->assertResponseOk();
        
        $responseData = json_decode($response->response->getContent(), true);
        $this->assertGreaterThanOrEqual(3, $responseData['meta']['count']);

        // Verify all our tasks are in the trash
        $trashedTaskIds = array_column($responseData['data'], 'id');
        foreach ($taskIds as $taskId) {
            $this->assertContains($taskId, $trashedTaskIds);
        }
    }

    /**
     * Test logging for soft delete operations
     */
    public function testSoftDeleteLogging()
    {
        // Create a task
        $taskData = [
            'title' => 'Task for Logging Test',
            'description' => 'Testing soft delete logging',
            'status' => 'pending',
            'created_by' => 1,
        ];

        $response = $this->post('/tasks', $taskData);
        $responseData = json_decode($response->response->getContent(), true);
        $taskId = $responseData['data']['id'];

        // Soft delete (should log 'deleted' action)
        $this->delete("/tasks/{$taskId}");

        // Restore (should log 'restored' action)
        $this->post("/tasks/{$taskId}/restore");

        // Force delete (should log 'force_deleted' action)
        $this->delete("/tasks/{$taskId}/force");

        // Check if logs were created
        $logs = TaskLog::where('task_id', $taskId)->get();
        
        $logActions = $logs->pluck('action')->toArray();
        $this->assertContains('created', $logActions);
        $this->assertContains('deleted', $logActions);
        $this->assertContains('restored', $logActions);
        $this->assertContains('force_deleted', $logActions);
    }

    /**
     * Test invalid task ID validation
     */
    public function testInvalidTaskIdValidation()
    {
        // Test with non-numeric ID
        $response = $this->post('/tasks/abc/restore');
        $response->assertResponseStatus(404); // Route not found due to regex constraint

        $response = $this->delete('/tasks/abc/force');
        $response->assertResponseStatus(404); // Route not found due to regex constraint
    }
}