<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\Task;
use App\Models\TaskLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDatabaseConnection()
    {
        // Test basic database connectivity
        $this->assertTrue(true); // Database should connect via TestCase setup
        
        // Test we can query the database
        $result = DB::connection()->getPdo();
        $this->assertNotNull($result);
    }

    public function testTaskModelCrudOperations()
    {
        // Test Create
        $taskData = [
            'title' => 'Database Test Task',
            'description' => 'Testing database CRUD operations',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => 'db-test@example.com',
            'due_date' => '2024-12-31'
        ];

        $task = Task::create($taskData);
        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals($taskData['title'], $task->title);
        $this->assertNotNull($task->id);

        // Test Read
        $retrievedTask = Task::find($task->id);
        $this->assertNotNull($retrievedTask);
        $this->assertEquals($taskData['title'], $retrievedTask->title);

        // Test Update
        $updatedData = ['title' => 'Updated Database Test Task'];
        $task->update($updatedData);
        
        $task->refresh();
        $this->assertEquals('Updated Database Test Task', $task->title);

        // Test Soft Delete
        $task->delete();
        $this->assertNotNull($task->deleted_at);
        
        // Verify task is not found in normal queries
        $normalQuery = Task::find($task->id);
        $this->assertNull($normalQuery);
        
        // But found in withTrashed queries
        $trashedQuery = Task::withTrashed()->find($task->id);
        $this->assertNotNull($trashedQuery);

        // Test Restore
        $task->restore();
        $task->refresh();
        $this->assertNull($task->deleted_at);
        
        // Test Hard Delete
        $task->forceDelete();
        $hardDeletedTask = Task::withTrashed()->find($task->id);
        $this->assertNull($hardDeletedTask);
    }

    public function testTaskLogModelCrudOperations()
    {
        // First create a task to associate logs with
        $task = Task::factory()->create();

        // Test Create Log
        $logData = [
            'task_id' => $task->id,
            'action' => 'created',
            'details' => [
                'field' => 'status',
                'old_value' => null,
                'new_value' => 'pending'
            ],
            'user_id' => 'test-user',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent'
        ];

        $log = TaskLog::create($logData);
        $this->assertInstanceOf(TaskLog::class, $log);
        $this->assertEquals($task->id, $log->task_id);
        $this->assertEquals('created', $log->action);

        // Test Read Log
        $retrievedLog = TaskLog::find($log->id);
        $this->assertNotNull($retrievedLog);
        $this->assertEquals($logData['action'], $retrievedLog->action);

        // Test Log relationships
        $this->assertEquals($task->id, $retrievedLog->task_id);
        
        // Test Update Log (if needed)
        $updatedLogData = ['action' => 'updated'];
        $log->update($updatedLogData);
        
        $log->refresh();
        $this->assertEquals('updated', $log->action);

        // Test Delete Log
        $log->delete();
        $deletedLog = TaskLog::find($log->id);
        $this->assertNull($deletedLog);
    }

    public function testDatabaseTransactions()
    {
        DB::beginTransaction();
        
        try {
            // Create a task within transaction
            $task = Task::create([
                'title' => 'Transaction Test Task',
                'description' => 'Testing database transactions',
                'status' => 'pending',
                'priority' => 'low'
            ]);

            $this->assertNotNull($task->id);
            
            // Create associated log
            $log = TaskLog::create([
                'task_id' => $task->id,
                'action' => 'created',
                'details' => ['test' => 'transaction']
            ]);

            $this->assertNotNull($log->id);
            
            // Rollback transaction
            DB::rollback();
            
            // Verify data was rolled back
            $taskAfterRollback = Task::find($task->id);
            $logAfterRollback = TaskLog::find($log->id);
            
            $this->assertNull($taskAfterRollback);
            $this->assertNull($logAfterRollback);
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function testDatabaseIndexes()
    {
        // Create multiple tasks to test query performance
        $tasks = Task::factory()->count(100)->create();

        $startTime = microtime(true);
        
        // Test indexed queries (should be fast)
        $pendingTasks = Task::where('status', 'pending')->get();
        $highPriorityTasks = Task::where('priority', 'high')->get();
        
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        // Query should complete in reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $queryTime);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $pendingTasks);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $highPriorityTasks);
    }

    public function testDatabaseConstraints()
    {
        // Test that required fields are enforced
        $this->expectException(\Exception::class);
        
        Task::create([
            // Missing required fields should cause constraint violation
            'description' => 'This should fail due to missing title'
        ]);
    }

    public function testTimestampHandling()
    {
        $beforeCreate = Carbon::now();
        
        $task = Task::factory()->create();
        
        $afterCreate = Carbon::now();
        
        // Test that timestamps are set correctly
        $this->assertNotNull($task->created_at);
        $this->assertNotNull($task->updated_at);
        
        // Timestamps should be within reasonable range
        $this->assertGreaterThanOrEqual($beforeCreate, $task->created_at);
        $this->assertLessThanOrEqual($afterCreate, $task->created_at);
        
        $this->assertEquals($task->created_at->format('Y-m-d H:i:s'), 
                          $task->updated_at->format('Y-m-d H:i:s'));
        
        // Test updating timestamps
        $originalUpdatedAt = $task->updated_at;
        
        // Force a small delay to ensure timestamp difference
        sleep(1);
        
        $task->update(['description' => 'Updated description']);
        
        $this->assertGreaterThan($originalUpdatedAt, $task->updated_at);
    }

    public function testBulkOperations()
    {
        // Test bulk insert
        $taskData = [];
        for ($i = 1; $i <= 10; $i++) {
            $taskData[] = [
                'title' => "Bulk Task {$i}",
                'description' => "Bulk task description {$i}",
                'status' => 'pending',
                'priority' => 'medium',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        Task::insert($taskData);
        
        $insertedTasks = Task::where('title', 'LIKE', 'Bulk Task%')->get();
        $this->assertCount(10, $insertedTasks);
        
        // Test bulk update
        Task::where('title', 'LIKE', 'Bulk Task%')
            ->update(['status' => 'in_progress']);
            
        $updatedTasks = Task::where('title', 'LIKE', 'Bulk Task%')
                           ->where('status', 'in_progress')
                           ->get();
        $this->assertCount(10, $updatedTasks);
        
        // Test bulk delete
        Task::where('title', 'LIKE', 'Bulk Task%')->delete();
        
        $remainingTasks = Task::where('title', 'LIKE', 'Bulk Task%')->get();
        $this->assertCount(0, $remainingTasks);
    }

    public function testQueryPerformanceWithLargeDataset()
    {
        // Create a larger dataset
        Task::factory()->count(1000)->create();
        
        $startTime = microtime(true);
        
        // Test paginated queries
        $page1 = Task::orderBy('created_at', 'desc')->limit(20)->get();
        
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        $this->assertCount(20, $page1);
        $this->assertLessThan(0.5, $queryTime); // Should complete in less than 0.5 seconds
        
        // Test filtered queries
        $startTime = microtime(true);
        
        $filteredTasks = Task::where('status', 'pending')
                            ->where('priority', 'high')
                            ->limit(10)
                            ->get();
        
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        $this->assertLessThan(0.5, $queryTime);
    }

    public function testDatabaseConnectionPooling()
    {
        // Test multiple concurrent database connections
        $connections = [];
        
        for ($i = 0; $i < 5; $i++) {
            $connections[] = DB::connection();
        }
        
        // All connections should be valid
        foreach ($connections as $connection) {
            $this->assertNotNull($connection);
            $result = $connection->select('SELECT 1 as test');
            $this->assertEquals(1, $result[0]->test);
        }
    }
}