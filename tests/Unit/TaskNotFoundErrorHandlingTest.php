<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Exceptions\TaskNotFoundException;
use App\Exceptions\TaskRestoreException;

/**
 * Test proper error handling for non-existent tasks
 */
class TaskNotFoundErrorHandlingTest extends TestCase
{
    /**
     * Test TaskNotFoundException creation
     */
    public function testTaskNotFoundExceptionCreation()
    {
        $taskId = 99999;
        $operation = 'show';
        
        $exception = new TaskNotFoundException($taskId, $operation);
        
        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals($taskId, $exception->getTaskId());
        $this->assertEquals($operation, $exception->getOperation());
        $this->assertStringContainsString('Task with ID 99999 not found', $exception->getMessage());
    }

    /**
     * Test TaskNotFoundException::forOperation() static method
     */
    public function testTaskNotFoundExceptionForOperation()
    {
        $taskId = 12345;
        $operation = 'update';
        $context = ['requested_data' => ['title' => 'Test Title']];
        
        $exception = TaskNotFoundException::forOperation($taskId, $operation, $context);
        
        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals($taskId, $exception->getTaskId());
        $this->assertEquals($operation, $exception->getOperation());
        $this->assertEquals($context, $exception->getContext());
    }

    /**
     * Test TaskNotFoundException error details for API response
     */
    public function testTaskNotFoundExceptionErrorDetails()
    {
        $taskId = 67890;
        $operation = 'delete';
        
        $exception = TaskNotFoundException::forOperation($taskId, $operation);
        $errorDetails = $exception->getErrorDetails();
        
        $this->assertArrayHasKey('success', $errorDetails);
        $this->assertFalse($errorDetails['success']);
        
        $this->assertArrayHasKey('error', $errorDetails);
        $this->assertEquals('Task not found', $errorDetails['error']);
        
        $this->assertArrayHasKey('code', $errorDetails);
        $this->assertEquals('TASK_NOT_FOUND', $errorDetails['code']);
        
        $this->assertArrayHasKey('task_id', $errorDetails);
        $this->assertEquals($taskId, $errorDetails['task_id']);
        
        $this->assertArrayHasKey('operation', $errorDetails);
        $this->assertEquals($operation, $errorDetails['operation']);
        
        $this->assertArrayHasKey('suggestions', $errorDetails);
        $this->assertIsArray($errorDetails['suggestions']);
        $this->assertNotEmpty($errorDetails['suggestions']);
    }

    /**
     * Test TaskNotFoundException suggestions contain helpful messages
     */
    public function testTaskNotFoundExceptionSuggestions()
    {
        $exception = TaskNotFoundException::forOperation(123, 'update');
        $errorDetails = $exception->getErrorDetails();
        $suggestions = $errorDetails['suggestions'];
        
        $this->assertContains('Verify the task ID is correct', $suggestions);
        $this->assertContains('Check if the task was deleted', $suggestions);
        $this->assertContains('Use GET /tasks to list all available tasks', $suggestions);
        
        // Should have operation-specific suggestion for update
        $this->assertContains('Use GET /tasks/123 to verify the task exists before attempting to update', $suggestions);
    }

    /**
     * Test TaskNotFoundException for different operations
     */
    public function testTaskNotFoundExceptionForDifferentOperations()
    {
        $operations = ['show', 'update', 'delete', 'restore', 'force_delete'];
        
        foreach ($operations as $operation) {
            $exception = TaskNotFoundException::forOperation(999, $operation);
            $errorDetails = $exception->getErrorDetails();
            
            $this->assertEquals('TASK_NOT_FOUND', $errorDetails['code']);
            $this->assertEquals(999, $errorDetails['task_id']);
            $this->assertEquals($operation, $errorDetails['operation']);
            $this->assertStringContainsString("during {$operation} operation", $exception->getMessage());
        }
    }

    /**
     * Test TaskRestoreException for different scenarios
     */
    public function testTaskRestoreExceptionScenarios()
    {
        // Test restore exception when task is not deleted
        $taskId = 456;
        $reason = 'Task is currently active';
        
        $exception = new TaskRestoreException(
            'Task is not deleted and cannot be restored',
            'restore',
            $taskId,
            $reason
        );
        
        $this->assertEquals(409, $exception->getCode()); // Conflict status code
        $this->assertEquals($taskId, $exception->getTaskId());
        $this->assertEquals('restore', $exception->getOperation());
    }

    /**
     * Test that validation helper correctly validates task IDs
     */
    public function testTaskIdValidation()
    {
        $validIds = [1, 123, 999999];
        $invalidIds = [-1, 0, 'abc', null, ''];
        
        foreach ($validIds as $id) {
            $result = \App\Http\Requests\ValidationHelper::validateTaskId($id);
            $this->assertEquals($id, $result);
        }
        
        foreach ($invalidIds as $id) {
            $this->expectException(\App\Exceptions\TaskValidationException::class);
            \App\Http\Requests\ValidationHelper::validateTaskId($id);
        }
    }

    /**
     * Test bulk operation exception
     */
    public function testBulkTaskNotFoundException()
    {
        $taskIds = [123, 456, 789];
        $operation = 'bulk_delete';
        
        $exception = TaskNotFoundException::forBulkOperation($taskIds, $operation);
        $errorDetails = $exception->getErrorDetails();
        
        $this->assertEquals('TASK_NOT_FOUND', $errorDetails['code']);
        $this->assertStringContainsString('Multiple tasks not found', $exception->getMessage());
        $this->assertStringContainsString($operation, $exception->getMessage());
    }
}