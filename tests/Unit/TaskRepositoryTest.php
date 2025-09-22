<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Repositories\TaskRepository;
use App\Services\SqlInjectionProtectionService;
use App\Models\Task;
use Mockery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TaskRepositoryTest extends TestCase
{
    protected $sqlProtectionService;
    protected $taskRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlProtectionService = Mockery::mock(SqlInjectionProtectionService::class);
        $this->taskRepository = new TaskRepository($this->sqlProtectionService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testFindAllReturnsAllTasks()
    {
        // Create some test tasks
        Task::factory()->create(['status' => 'pending']);
        Task::factory()->create(['status' => 'completed']);

        $result = $this->taskRepository->findAll();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function testFindAllWithStatusFilter()
    {
        $status = 'pending';
        
        $this->sqlProtectionService->shouldReceive('sanitizeInput')
            ->once()
            ->with($status, 'task_status_filter')
            ->andReturn($status);

        // Create test tasks with different statuses
        Task::factory()->create(['status' => 'pending']);
        Task::factory()->create(['status' => 'completed']);

        $result = $this->taskRepository->findAll($status);

        $this->assertInstanceOf(Collection::class, $result);
        // This would depend on actual database setup
    }

    public function testFindByIdReturnsTask()
    {
        $task = Task::factory()->create();

        $result = $this->taskRepository->findById($task->id);

        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($task->id, $result->id);
    }

    public function testFindByIdReturnsNullForNonExistentTask()
    {
        $result = $this->taskRepository->findById(999999);

        $this->assertNull($result);
    }

    public function testCreateTask()
    {
        $taskData = [
            'title' => 'Test Task',
            'description' => 'Test Description',
            'status' => 'pending',
            'priority' => 'high',
            'assigned_to' => 'test@example.com',
            'due_date' => '2024-12-31'
        ];

        $result = $this->taskRepository->create($taskData);

        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($taskData['title'], $result->title);
        $this->assertEquals($taskData['description'], $result->description);
        $this->assertEquals($taskData['status'], $result->status);
    }

    public function testUpdateTask()
    {
        $task = Task::factory()->create(['title' => 'Original Title']);
        $updateData = ['title' => 'Updated Title'];

        $result = $this->taskRepository->update($task->id, $updateData);

        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals('Updated Title', $result->title);
    }

    public function testUpdateNonExistentTaskThrowsException()
    {
        $this->expectException(ModelNotFoundException::class);

        $this->taskRepository->update(999999, ['title' => 'Updated']);
    }

    public function testDeleteTask()
    {
        $task = Task::factory()->create();

        $result = $this->taskRepository->delete($task->id);

        $this->assertTrue($result);
        $this->assertNull(Task::find($task->id));
    }

    public function testDeleteNonExistentTaskThrowsException()
    {
        $this->expectException(ModelNotFoundException::class);

        $this->taskRepository->delete(999999);
    }

    public function testSoftDeleteTask()
    {
        $task = Task::factory()->create();

        $result = $this->taskRepository->softDelete($task->id);

        $this->assertTrue($result);
        $this->assertNotNull($task->fresh()->deleted_at);
    }

    public function testRestoreTask()
    {
        $task = Task::factory()->create();
        $task->delete(); // Soft delete

        $result = $this->taskRepository->restore($task->id);

        $this->assertTrue($result);
        $this->assertNull($task->fresh()->deleted_at);
    }

    public function testFindByStatusReturnsCorrectTasks()
    {
        $status = 'pending';
        
        $this->sqlProtectionService->shouldReceive('sanitizeInput')
            ->once()
            ->with($status, 'task_status_filter')
            ->andReturn($status);

        Task::factory()->create(['status' => 'pending']);
        Task::factory()->create(['status' => 'completed']);

        $result = $this->taskRepository->findByStatus($status);

        $this->assertInstanceOf(Collection::class, $result);
        // All returned tasks should have the specified status
        foreach ($result as $task) {
            $this->assertEquals($status, $task->status);
        }
    }

    public function testFindByPriorityReturnsCorrectTasks()
    {
        $priority = 'high';
        
        $this->sqlProtectionService->shouldReceive('sanitizeInput')
            ->once()
            ->with($priority, 'task_priority_filter')
            ->andReturn($priority);

        Task::factory()->create(['priority' => 'high']);
        Task::factory()->create(['priority' => 'low']);

        $result = $this->taskRepository->findByPriority($priority);

        $this->assertInstanceOf(Collection::class, $result);
        foreach ($result as $task) {
            $this->assertEquals($priority, $task->priority);
        }
    }
}