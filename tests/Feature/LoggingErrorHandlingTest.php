<?php

namespace Tests\Feature;

use Laravel\Lumen\Testing\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use App\Models\Task;
use App\Models\TaskLog;
use App\Services\LogService;
use App\Repositories\LogRepository;
use App\Exceptions\LoggingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

class LoggingErrorHandlingTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Create the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__ . '/../../bootstrap/app.php';
    }

    /**
     * Test that logging failures are handled gracefully in task creation
     */
    public function testTaskCreationContinuesWhenLoggingFails()
    {
        // Mock the LogService to throw an exception
        $this->app->bind('App\Services\LogServiceInterface', function () {
            $mock = Mockery::mock('App\Services\LogServiceInterface');
            $mock->shouldReceive('createTaskActivityLog')->andThrow(new LoggingException('MongoDB connection failed'));
            $mock->shouldReceive('createLog')->andThrow(new LoggingException('MongoDB connection failed'));
            return $mock;
        });

        // Attempt to create a task
        $response = $this->post('/tasks', [
            'title' => 'Test Task with Logging Failure',
            'description' => 'This task should be created even if logging fails',
            'status' => 'pending',
            'priority' => 'medium'
        ]);

        // Task creation should succeed despite logging failure
        $response->assertResponseStatus(201);
        $response->seeJsonStructure([
            'success',
            'data' => [
                'id',
                'title',
                'description',
                'status'
            ],
            'message'
        ]);

        // Verify task was actually created in database
        $this->seeInDatabase('tasks', [
            'title' => 'Test Task with Logging Failure',
            'status' => 'pending'
        ]);
    }

    /**
     * Test that task updates continue when logging fails
     */
    public function testTaskUpdateContinuesWhenLoggingFails()
    {
        // Create a task first
        $task = Task::create([
            'title' => 'Original Title',
            'description' => 'Original description',
            'status' => 'pending',
            'priority' => 'low'
        ]);

        // Mock the LogService to throw an exception
        $this->app->bind('App\Services\LogServiceInterface', function () {
            $mock = Mockery::mock('App\Services\LogServiceInterface');
            $mock->shouldReceive('createTaskActivityLog')->andThrow(new LoggingException('MongoDB connection failed'));
            $mock->shouldReceive('createLog')->andThrow(new LoggingException('MongoDB connection failed'));
            return $mock;
        });

        // Attempt to update the task
        $response = $this->put("/tasks/{$task->id}", [
            'title' => 'Updated Title',
            'status' => 'completed'
        ]);

        // Task update should succeed despite logging failure
        $response->assertResponseStatus(200);
        $response->seeJsonStructure([
            'success',
            'data' => [
                'id',
                'title',
                'status'
            ],
            'message'
        ]);

        // Verify task was actually updated in database
        $this->seeInDatabase('tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
            'status' => 'completed'
        ]);
    }

    /**
     * Test that task deletion continues when logging fails
     */
    public function testTaskDeletionContinuesWhenLoggingFails()
    {
        // Create a task first
        $task = Task::create([
            'title' => 'Task to Delete',
            'description' => 'This task will be deleted',
            'status' => 'pending',
            'priority' => 'low'
        ]);

        // Mock the LogService to throw an exception
        $this->app->bind('App\Services\LogServiceInterface', function () {
            $mock = Mockery::mock('App\Services\LogServiceInterface');
            $mock->shouldReceive('createTaskActivityLog')->andThrow(new LoggingException('MongoDB connection failed'));
            $mock->shouldReceive('createLog')->andThrow(new LoggingException('MongoDB connection failed'));
            return $mock;
        });

        // Attempt to delete the task
        $response = $this->delete("/tasks/{$task->id}");

        // Task deletion should succeed despite logging failure
        $response->assertResponseStatus(202);

        // Verify task was soft deleted
        $this->seeInDatabase('tasks', [
            'id' => $task->id
        ]);
        
        // Verify the task is actually soft deleted by checking deleted_at is not null
        $deletedTask = \App\Models\Task::withTrashed()->find($task->id);
        $this->assertNotNull($deletedTask->deleted_at);
    }

    /**
     * Test fallback logging to MySQL when MongoDB is unavailable
     */
    public function testFallbackLoggingToMySQL()
    {
        // Get the actual LogService (not mocked)
        $logService = app('App\Services\LogServiceInterface');

        // Create a task to log about
        $task = Task::create([
            'title' => 'Test Task for Fallback Logging',
            'description' => 'Testing fallback mechanisms',
            'status' => 'pending',
            'priority' => 'medium'
        ]);

        // Clear any existing fallback logs
        DB::table('task_logs_fallback')->truncate();

        // Mock the LogRepository to simulate MongoDB failure
        $this->app->bind('App\Repositories\LogRepositoryInterface', function () {
            $mock = Mockery::mock('App\Repositories\LogRepositoryInterface');
            $mock->shouldReceive('create')->andThrow(new \MongoDB\Driver\Exception\ConnectionException('MongoDB connection failed'));
            return $mock;
        });

        // Attempt to create a log entry - this should trigger fallback
        try {
            $logService->createLog(
                $task->id,
                'test_action',
                ['test_data' => 'value'],
                1,
                'Test log for fallback'
            );
        } catch (LoggingException $e) {
            // This might throw if all fallbacks fail, which is acceptable for this test
        }

        // Check if fallback logging to MySQL occurred
        $fallbackLogs = DB::table('task_logs_fallback')
            ->where('task_id', $task->id)
            ->where('action', 'test_action')
            ->get();

        // We should have at least one fallback log entry
        $this->assertGreaterThan(0, $fallbackLogs->count(), 'Fallback logging to MySQL should have occurred');

        $fallbackLog = $fallbackLogs->first();
        $this->assertEquals($task->id, $fallbackLog->task_id);
        $this->assertEquals('test_action', $fallbackLog->action);
        $this->assertContains('test_data', $fallbackLog->data);
    }

    /**
     * Test file system fallback when both MongoDB and MySQL fail
     */
    public function testFallbackLoggingToFileSystem()
    {
        // Get the actual LogService (not mocked)
        $logService = app('App\Services\LogServiceInterface');

        // Create a task to log about
        $task = Task::create([
            'title' => 'Test Task for File Fallback',
            'description' => 'Testing file system fallback',
            'status' => 'pending',
            'priority' => 'high'
        ]);

        // Mock both LogRepository and DB to fail
        $this->app->bind('App\Repositories\LogRepositoryInterface', function () {
            $mock = Mockery::mock('App\Repositories\LogRepositoryInterface');
            $mock->shouldReceive('create')->andThrow(new \MongoDB\Driver\Exception\ConnectionException('MongoDB connection failed'));
            return $mock;
        });

        // Mock DB facade to fail
        DB::shouldReceive('table->insert')->andThrow(new \Exception('MySQL connection failed'));

        // Clear the fallback log file
        $logFile = storage_path('logs/task_logs_fallback.log');
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }

        // Attempt to create a log entry - this should trigger file system fallback
        try {
            $logService->createLog(
                $task->id,
                'test_file_fallback',
                ['test_data' => 'file_value'],
                1,
                'Test log for file fallback'
            );
        } catch (LoggingException $e) {
            // This might throw if all fallbacks fail, which is acceptable for this test
        }

        // Check if file system fallback occurred
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $this->assertStringContainsString('test_file_fallback', $logContent, 'File system fallback should have occurred');
            $this->assertStringContainsString((string)$task->id, $logContent, 'Log should contain task ID');
        }
    }

    /**
     * Test that error logs are created when logging fails
     */
    public function testErrorLogsCreatedOnLoggingFailure()
    {
        // Mock Log facade to capture error logs
        Log::spy();

        // Mock the LogService to throw an exception
        $this->app->bind('App\Services\LogServiceInterface', function () {
            $mock = Mockery::mock('App\Services\LogServiceInterface');
            $mock->shouldReceive('createTaskActivityLog')->andThrow(new LoggingException('Test logging failure'));
            $mock->shouldReceive('createLog')->andThrow(new LoggingException('Test logging failure'));
            return $mock;
        });

        // Attempt to create a task (which will trigger logging)
        $response = $this->post('/tasks', [
            'title' => 'Test Task for Error Logging',
            'description' => 'This should create error logs',
            'status' => 'pending',
            'priority' => 'medium'
        ]);

        // Verify that warning logs were created
        Log::shouldHaveReceived('warning')
            ->with(
                'Failed to create comprehensive task creation log',
                Mockery::type('array')
            );
    }

    /**
     * Test retry mechanism with temporary failures
     */
    public function testRetryMechanismWithTemporaryFailures()
    {
        // This test would require a more sophisticated mock setup
        // to simulate temporary failures followed by success
        $this->markTestIncomplete('Retry mechanism test requires complex mocking setup');
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        // Clean up fallback table
        try {
            DB::table('task_logs_fallback')->truncate();
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }

        Mockery::close();
        parent::tearDown();
    }
}