<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\TaskLog;
use App\Models\Task;

class LogApiIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testGetAllLogsEndpoint()
    {
        // Create test task and logs
        $task = Task::factory()->create();
        TaskLog::factory()->count(5)->create(['task_id' => $task->id]);

        $this->get('/api/v1/logs')
            ->assertResponseStatus(200);

        $this->assertApiSuccess($this->response);
        
        $responseData = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
    }

    public function testGetLogsWithPagination()
    {
        $task = Task::factory()->create();
        TaskLog::factory()->count(15)->create(['task_id' => $task->id]);

        $this->get('/api/v1/logs?page=1&per_page=5')
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('pagination', $responseData);
        $this->assertEquals(1, $responseData['pagination']['current_page']);
        $this->assertLessThanOrEqual(5, count($responseData['data']));
    }

    public function testGetLogsByTaskId()
    {
        $task1 = Task::factory()->create();
        $task2 = Task::factory()->create();
        
        // Create logs for different tasks
        TaskLog::factory()->count(3)->create(['task_id' => $task1->id]);
        TaskLog::factory()->count(2)->create(['task_id' => $task2->id]);

        $this->get("/api/v1/logs?task_id={$task1->id}")
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        
        // All returned logs should belong to task1
        foreach ($responseData['data'] as $log) {
            $this->assertEquals($task1->id, $log['task_id']);
        }
    }

    public function testGetLogsFilteredByAction()
    {
        $task = Task::factory()->create();
        
        // Create logs with different actions
        TaskLog::factory()->count(2)->create([
            'task_id' => $task->id,
            'action' => 'created'
        ]);
        TaskLog::factory()->count(3)->create([
            'task_id' => $task->id,
            'action' => 'updated'
        ]);

        $this->get('/api/v1/logs?action=created')
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        
        // All returned logs should have action 'created'
        foreach ($responseData['data'] as $log) {
            $this->assertEquals('created', $log['action']);
        }
    }

    public function testGetLogsFilteredByDateRange()
    {
        $task = Task::factory()->create();
        
        // Create logs with different timestamps
        TaskLog::factory()->create([
            'task_id' => $task->id,
            'created_at' => '2024-01-15 10:00:00'
        ]);
        TaskLog::factory()->create([
            'task_id' => $task->id,
            'created_at' => '2024-06-15 10:00:00'
        ]);
        TaskLog::factory()->create([
            'task_id' => $task->id,
            'created_at' => '2024-12-15 10:00:00'
        ]);

        $this->get('/api/v1/logs?start_date=2024-05-01&end_date=2024-07-01')
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        
        foreach ($responseData['data'] as $log) {
            $logDate = date('Y-m-d', strtotime($log['created_at']));
            $this->assertGreaterThanOrEqual('2024-05-01', $logDate);
            $this->assertLessThanOrEqual('2024-07-01', $logDate);
        }
    }

    public function testGetSingleLogEntry()
    {
        $task = Task::factory()->create();
        $log = TaskLog::factory()->create([
            'task_id' => $task->id,
            'action' => 'created'
        ]);

        $this->get("/api/v1/logs/{$log->id}")
            ->assertResponseStatus(200);

        $this->assertApiSuccess($this->response);
        
        $responseData = json_decode($this->response->getContent(), true);
        $this->assertEquals($log->id, $responseData['data']['id']);
        $this->assertEquals('created', $responseData['data']['action']);
    }

    public function testGetNonExistentLog()
    {
        $nonExistentId = 999999;

        $this->get("/api/v1/logs/{$nonExistentId}")
            ->assertResponseStatus(404);

        $this->assertApiError($this->response, 'Log entry not found', 404);
    }

    public function testLogCreationWhenTaskIsCreated()
    {
        $taskData = $this->createTestTask();

        $this->json('POST', '/api/v1/tasks', $taskData)
            ->assertResponseStatus(201);

        // Verify a log entry was created
        $task = Task::where('title', $taskData['title'])->first();
        $this->assertNotNull($task);

        $logs = TaskLog::where('task_id', $task->id)->get();
        $this->assertGreaterThan(0, $logs->count());

        // Check if there's a 'created' action log
        $createdLog = $logs->where('action', 'created')->first();
        $this->assertNotNull($createdLog);
    }

    public function testLogCreationWhenTaskIsUpdated()
    {
        $task = Task::factory()->create(['status' => 'pending']);
        
        $updateData = ['status' => 'in_progress'];

        $this->json('PUT', "/api/v1/tasks/{$task->id}", $updateData)
            ->assertResponseStatus(200);

        // Verify an update log entry was created
        $logs = TaskLog::where('task_id', $task->id)
            ->where('action', 'updated')
            ->get();
        
        $this->assertGreaterThan(0, $logs->count());
        
        $updateLog = $logs->first();
        $this->assertArrayHasKey('old_values', $updateLog->details);
        $this->assertArrayHasKey('new_values', $updateLog->details);
    }

    public function testLogCreationWhenTaskIsDeleted()
    {
        $task = Task::factory()->create();

        $this->json('DELETE', "/api/v1/tasks/{$task->id}")
            ->assertResponseStatus(200);

        // Verify a delete log entry was created
        $logs = TaskLog::where('task_id', $task->id)
            ->where('action', 'deleted')
            ->get();
        
        $this->assertGreaterThan(0, $logs->count());
    }

    public function testLogCreationWhenTaskIsRestored()
    {
        $task = Task::factory()->create();
        $task->delete(); // Soft delete first

        $this->json('POST', "/api/v1/tasks/{$task->id}/restore")
            ->assertResponseStatus(200);

        // Verify a restore log entry was created
        $logs = TaskLog::where('task_id', $task->id)
            ->where('action', 'restored')
            ->get();
        
        $this->assertGreaterThan(0, $logs->count());
    }

    public function testLogSearchFunctionality()
    {
        $task = Task::factory()->create();
        
        // Create logs with searchable details
        TaskLog::factory()->create([
            'task_id' => $task->id,
            'action' => 'updated',
            'details' => ['field' => 'priority', 'old_value' => 'low', 'new_value' => 'high']
        ]);
        
        TaskLog::factory()->create([
            'task_id' => $task->id,
            'action' => 'updated',
            'details' => ['field' => 'status', 'old_value' => 'pending', 'new_value' => 'completed']
        ]);

        $this->get('/api/v1/logs?search=priority')
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        
        // Should return logs that contain "priority" in details
        $found = false;
        foreach ($responseData['data'] as $log) {
            if (isset($log['details']['field']) && $log['details']['field'] === 'priority') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should find logs containing priority in details');
    }

    public function testLogStatisticsEndpoint()
    {
        $task = Task::factory()->create();
        
        // Create various log entries
        TaskLog::factory()->count(3)->create(['task_id' => $task->id, 'action' => 'created']);
        TaskLog::factory()->count(5)->create(['task_id' => $task->id, 'action' => 'updated']);
        TaskLog::factory()->count(2)->create(['task_id' => $task->id, 'action' => 'deleted']);

        $this->get('/api/v1/logs/statistics')
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('total_logs', $responseData['data']);
        $this->assertArrayHasKey('actions_breakdown', $responseData['data']);
    }

    public function testBulkLogDeletion()
    {
        $task = Task::factory()->create();
        $logs = TaskLog::factory()->count(5)->create(['task_id' => $task->id]);
        $logIds = $logs->pluck('id')->toArray();

        $this->json('DELETE', '/api/v1/logs/bulk-delete', ['log_ids' => $logIds])
            ->assertResponseStatus(200);

        $this->assertApiSuccess($this->response, 'Logs deleted successfully');
        
        // Verify logs were deleted
        foreach ($logIds as $logId) {
            $log = TaskLog::find($logId);
            $this->assertNull($log);
        }
    }

    public function testLogExportFunctionality()
    {
        $task = Task::factory()->create();
        TaskLog::factory()->count(10)->create(['task_id' => $task->id]);

        $this->get('/api/v1/logs/export?format=json')
            ->assertResponseStatus(200);

        $responseData = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('export_info', $responseData);
    }
}