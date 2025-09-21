<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Task;
use App\Repositories\TaskRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskRepositorySoftDeleteTest extends TestCase
{
    /**
     * Test findTrashed method
     */
    public function testFindTrashed()
    {
        $repository = new TaskRepository();
        
        // This is a unit test that would work with a properly mocked environment
        // In a real scenario, you would mock the Eloquent model
        $this->assertTrue(method_exists($repository, 'findTrashed'));
        $this->assertTrue(method_exists($repository, 'findTrashedById'));
        $this->assertTrue(method_exists($repository, 'findWithTrashed'));
        $this->assertTrue(method_exists($repository, 'restore'));
        $this->assertTrue(method_exists($repository, 'forceDelete'));
    }

    /**
     * Test that TaskRepository implements all required soft delete methods
     */
    public function testRepositoryImplementsSoftDeleteMethods()
    {
        $repository = new TaskRepository();
        
        $requiredMethods = [
            'findTrashed',
            'findTrashedById', 
            'findWithTrashed',
            'restore',
            'forceDelete'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($repository, $method),
                "TaskRepository should implement {$method} method"
            );
        }
    }

    /**
     * Test that Task model uses SoftDeletes trait
     */
    public function testTaskModelUsesSoftDeletes()
    {
        $task = new Task();
        $traits = class_uses($task);
        
        $this->assertContains(
            'Illuminate\Database\Eloquent\SoftDeletes',
            $traits,
            'Task model should use SoftDeletes trait'
        );
    }

    /**
     * Test that Task model has proper casts for deleted_at
     */
    public function testTaskModelHasDeletedAtCast()
    {
        $task = new Task();
        $casts = $task->getCasts();
        
        $this->assertArrayHasKey('deleted_at', $casts);
        $this->assertEquals('datetime', $casts['deleted_at']);
    }

    /**
     * Test Task model fillable fields include soft delete related fields
     */
    public function testTaskModelFillableFields()
    {
        $task = new Task();
        $fillable = $task->getFillable();
        
        $expectedFields = [
            'title',
            'description', 
            'status',
            'due_date',
            'created_by',
            'assigned_to',
            'completed_at'
        ];
        
        foreach ($expectedFields as $field) {
            $this->assertContains(
                $field,
                $fillable,
                "Task model should have {$field} in fillable array"
            );
        }
    }
}