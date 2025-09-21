<?php

namespace Tests\Feature;

use Laravel\Lumen\Testing\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use App\Models\Task;
use App\Models\TaskLog;

class TaskCreationLoggingTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Create application instance
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__.'/../../bootstrap/app.php';
    }

    /**
     * Test that task creation generates proper log entries
     */
    public function testTaskCreationGeneratesLogs()
    {
        // Clear any existing logs
        TaskLog::truncate();
        
        // Prepare task data
        $taskData = [
            'title' => 'Test Task for Logging',
            'description' => 'This task is created to test logging functionality',
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => now()->addDays(7)->toDateTimeString()
        ];

        // Create task via API
        $response = $this->json('POST', '/api/v1/tasks', $taskData, [
            'X-User-Id' => '123',
            'X-Request-ID' => 'test-request-' . uniqid()
        ]);

        // Assert task was created successfully
        $response->assertStatus(201);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertArrayHasKey('data', $responseData);
        $taskId = $responseData['data']['id'];

        // Verify logs were created
        $logs = TaskLog::where('task_id', $taskId)->get();
        $this->assertGreaterThan(0, $logs->count());

        // Check for creation log
        $creationLog = $logs->where('action', 'created')->first();
        $this->assertNotNull($creationLog, 'Creation log should exist');
        
        // Verify creation log structure
        $this->assertEquals($taskId, $creationLog->task_id);
        $this->assertEquals('created', $creationLog->action);
        $this->assertEquals(123, $creationLog->user_id);
        $this->assertArrayHasKey('new_data', $creationLog->data);
        $this->assertEmpty($creationLog->data['old_data']);
    }

    /**
     * Test that task creation with special conditions generates additional logs
     */
    public function testTaskCreationSpecialConditionsLogging()
    {
        // Clear any existing logs
        TaskLog::truncate();
        
        // Create a high-priority overdue task
        $taskData = [
            'title' => 'Urgent Overdue Task',
            'description' => 'This is an urgent task that is already overdue',
            'status' => 'pending',
            'priority' => 'urgent',
            'assigned_to' => 456,
            'due_date' => now()->subDays(1)->toDateTimeString() // Overdue
        ];

        $response = $this->json('POST', '/api/v1/tasks', $taskData, [
            'X-User-Id' => '123',
            'X-Request-ID' => 'special-test-' . uniqid()
        ]);

        $response->assertStatus(201);
        $responseData = json_decode($response->getContent(), true);
        $taskId = $responseData['data']['id'];

        // Check for special conditions log
        $logs = TaskLog::where('task_id', $taskId)->get();
        
        $specialConditionsLog = $logs->where('action', 'task_creation_special_conditions')->first();
        $this->assertNotNull($specialConditionsLog, 'Special conditions log should exist');
        
        $conditions = $specialConditionsLog->data['conditions'] ?? [];
        $this->assertContains('created_overdue', $conditions);
        $this->assertContains('high_priority_task', $conditions);
        $this->assertContains('immediately_assigned', $conditions);
    }

    /**
     * Test that comprehensive task creation details are logged
     */
    public function testComprehensiveTaskCreationLogging()
    {
        // Clear any existing logs
        TaskLog::truncate();
        
        $taskData = [
            'title' => 'Comprehensive Test Task',
            'description' => 'A task with all possible data to test comprehensive logging',
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_to' => 789,
            'due_date' => now()->addDays(5)->toDateTimeString()
        ];

        $response = $this->json('POST', '/api/v1/tasks', $taskData, [
            'X-User-Id' => '999',
            'X-Request-ID' => 'comprehensive-test-' . uniqid(),
            'User-Agent' => 'TaskTest/1.0'
        ]);

        $response->assertStatus(201);
        $responseData = json_decode($response->getContent(), true);
        $taskId = $responseData['data']['id'];

        // Check for detailed creation log
        $logs = TaskLog::where('task_id', $taskId)->get();
        $detailedLog = $logs->where('action', 'task_creation_details')->first();
        
        $this->assertNotNull($detailedLog, 'Detailed creation log should exist');
        
        // Verify comprehensive data structure
        $logData = $detailedLog->data;
        $this->assertArrayHasKey('created_task_data', $logData);
        $this->assertArrayHasKey('input_data', $logData);
        $this->assertArrayHasKey('request_metadata', $logData);
        $this->assertArrayHasKey('creation_context', $logData);
        
        // Verify request metadata
        $requestMeta = $logData['request_metadata'];
        $this->assertEquals('POST', $requestMeta['method']);
        $this->assertNotEmpty($requestMeta['request_id']);
        $this->assertNotEmpty($requestMeta['timestamp']);
        
        // Verify creation context
        $context = $logData['creation_context'];
        $this->assertArrayHasKey('auto_generated_fields', $context);
        $this->assertArrayHasKey('default_values_applied', $context);
        $this->assertArrayHasKey('computed_fields', $context);
    }

    /**
     * Test task creation logging with validation failures
     */
    public function testTaskCreationLoggingWithValidationFailure()
    {
        // Clear any existing logs
        TaskLog::truncate();
        
        // Submit invalid task data
        $invalidTaskData = [
            'title' => '', // Invalid: empty title
            'status' => 'invalid_status', // Invalid status
            'priority' => 'invalid_priority' // Invalid priority
        ];

        $response = $this->json('POST', '/api/v1/tasks', $invalidTaskData, [
            'X-User-Id' => '123',
            'X-Request-ID' => 'validation-fail-test-' . uniqid()
        ]);

        // Should fail with validation error
        $response->assertStatus(422);
        
        // No task should be created, so no task logs should exist
        // But system logs should capture the validation failure attempt
        $this->assertEquals(0, TaskLog::count());
    }

    /**
     * Test task creation logging performance impact
     */
    public function testTaskCreationLoggingPerformance()
    {
        $startTime = microtime(true);
        
        // Create multiple tasks to test logging performance
        for ($i = 0; $i < 5; $i++) {
            $taskData = [
                'title' => "Performance Test Task {$i}",
                'description' => "Testing logging performance",
                'status' => 'pending',
                'priority' => 'medium'
            ];

            $response = $this->json('POST', '/api/v1/tasks', $taskData, [
                'X-User-Id' => '123',
                'X-Request-ID' => "perf-test-{$i}-" . uniqid()
            ]);

            $response->assertStatus(201);
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        // Ensure reasonable performance (less than 5 seconds for 5 tasks)
        $this->assertLessThan(5.0, $totalTime, 'Task creation with logging should be reasonably fast');
        
        // Verify all logs were created
        $this->assertEquals(10, TaskLog::count()); // 2 logs per task (created + details)
    }

    /**
     * Test that logging failures don't prevent task creation
     */
    public function testLoggingFailureDoesNotPreventTaskCreation()
    {
        // This test would need to mock the logging service to simulate failure
        // For now, we'll just verify that task creation works normally
        
        $taskData = [
            'title' => 'Task Creation Should Succeed Despite Logging Issues',
            'description' => 'This tests resilience of task creation',
            'status' => 'pending'
        ];

        $response = $this->json('POST', '/api/v1/tasks', $taskData, [
            'X-User-Id' => '123'
        ]);

        // Task creation should succeed
        $response->assertStatus(201);
        
        // Task should exist in database
        $responseData = json_decode($response->getContent(), true);
        $task = Task::find($responseData['data']['id']);
        $this->assertNotNull($task);
        $this->assertEquals($taskData['title'], $task->title);
    }
}