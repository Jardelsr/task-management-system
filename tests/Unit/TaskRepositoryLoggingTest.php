<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;
use App\Repositories\TaskRepository;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class TaskRepositoryLoggingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that task repository logs creation attempts
     */
    public function testRepositoryLogsCreationAttempt()
    {
        // Mock Log facade
        Log::shouldReceive('info')
            ->once()
            ->with('Task creation initiated', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('Task created successfully', Mockery::type('array'));

        // Mock the Task model
        $mockTask = Mockery::mock(Task::class);
        $mockTask->shouldReceive('toArray')->andReturn([
            'id' => 1,
            'title' => 'Test Task',
            'status' => 'pending'
        ]);
        $mockTask->id = 1;
        $mockTask->title = 'Test Task';
        $mockTask->status = 'pending';
        $mockTask->priority = 'medium';
        $mockTask->assigned_to = null;
        $mockTask->due_date = null;
        $mockTask->created_at = now();

        // Mock Task::create
        Task::shouldReceive('create')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn($mockTask);

        // Test the repository
        $repository = new TaskRepository();
        $taskData = [
            'title' => 'Test Task',
            'description' => 'Test Description',
            'status' => 'pending'
        ];

        $result = $repository->create($taskData);
        
        $this->assertEquals($mockTask, $result);
    }

    /**
     * Test that repository properly prepares task data
     */
    public function testRepositoryPrepareTaskData()
    {
        $repository = new TaskRepository();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('prepareTaskData');
        $method->setAccessible(true);

        $inputData = [
            'title' => '  Test Task  ',
            'description' => '  Test Description  '
        ];

        $result = $method->invoke($repository, $inputData);

        // Check that defaults are applied
        $this->assertEquals(Task::STATUS_PENDING, $result['status']);
        $this->assertEquals('medium', $result['priority']);
        
        // Check that text is trimmed
        $this->assertEquals('Test Task', $result['title']);
        $this->assertEquals('Test Description', $result['description']);
    }

    /**
     * Test special conditions logging
     */
    public function testSpecialConditionsLogging()
    {
        // Mock task with special conditions
        $mockTask = Mockery::mock(Task::class);
        $mockTask->id = 1;
        $mockTask->priority = 'urgent';
        $mockTask->assigned_to = 123;
        $mockTask->due_date = now()->subDays(1); // Overdue
        
        // Mock Log to expect special conditions log
        Log::shouldReceive('info')
            ->once()
            ->with('Special task creation conditions detected', Mockery::on(function ($data) {
                return isset($data['conditions']) && 
                       in_array('overdue_on_creation', $data['conditions']) &&
                       in_array('high_priority', $data['conditions']) &&
                       in_array('assigned_on_creation', $data['conditions']);
            }));

        $repository = new TaskRepository();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('logSpecialCreationConditions');
        $method->setAccessible(true);

        $method->invoke($repository, $mockTask);
    }

    /**
     * Test database error logging
     */
    public function testDatabaseErrorLogging()
    {
        // Mock database exception
        $dbException = new \Illuminate\Database\QueryException(
            'mysql',
            'INSERT INTO tasks ...',
            [],
            new \Exception('Duplicate entry')
        );

        // Mock Log to expect error logging
        Log::shouldReceive('error')
            ->once()
            ->with('Database error during task create', Mockery::type('array'));

        $repository = new TaskRepository();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('logDatabaseError');
        $method->setAccessible(true);

        $method->invoke($repository, 'create', $dbException, ['test' => 'data']);
    }

    /**
     * Test date formatting in data preparation
     */
    public function testDateFormattingInDataPreparation()
    {
        $repository = new TaskRepository();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('prepareTaskData');
        $method->setAccessible(true);

        // Test valid date
        $inputData = [
            'title' => 'Test Task',
            'due_date' => '2025-12-31 15:30:00'
        ];

        $result = $method->invoke($repository, $inputData);
        $this->assertEquals('2025-12-31 15:30:00', $result['due_date']);

        // Mock Log for invalid date
        Log::shouldReceive('warning')
            ->once()
            ->with('Invalid due_date format in task creation', Mockery::type('array'));

        // Test invalid date
        $inputDataInvalid = [
            'title' => 'Test Task',
            'due_date' => 'invalid-date'
        ];

        $result = $method->invoke($repository, $inputDataInvalid);
        $this->assertArrayNotHasKey('due_date', $result);
    }
}