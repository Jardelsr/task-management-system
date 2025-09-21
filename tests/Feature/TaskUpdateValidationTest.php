<?php

namespace Tests\Feature;

use Laravel\Lumen\Testing\TestCase;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;
use App\Models\Task;

class TaskUpdateValidationTest extends TestCase
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
     * Test successful task update with valid data
     */
    public function testSuccessfulTaskUpdateWithValidData()
    {
        // Create a test task
        $task = Task::create([
            'title' => 'Original Title',
            'description' => 'Original Description',
            'status' => 'pending',
            'created_by' => 1,
            'assigned_to' => 1
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'status' => 'in_progress',
            'assigned_to' => 2
        ];

        $response = $this->put("/tasks/{$task->id}", $updateData);

        $response->assertResponseStatus(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'title',
                'status',
                'assigned_to',
                'updated_at'
            ],
            'timestamp'
        ]);

        // Verify data was actually updated
        $response->seeJson([
            'success' => true,
            'title' => 'Updated Title',
            'status' => 'in_progress',
            'assigned_to' => 2
        ]);

        // Verify X-Changed-Fields header is present
        $this->assertArrayHasKey('X-Changed-Fields', $response->response->headers->all());
    }

    /**
     * Test validation error for invalid title
     */
    public function testValidationErrorForInvalidTitle()
    {
        $task = Task::create([
            'title' => 'Original Title',
            'status' => 'pending',
            'created_by' => 1
        ]);

        // Test empty title
        $response = $this->put("/tasks/{$task->id}", ['title' => '']);
        $response->assertResponseStatus(422);
        $response->seeJsonStructure([
            'success',
            'error',
            'details',
            'code'
        ]);

        // Test title too short
        $response = $this->put("/tasks/{$task->id}", ['title' => 'AB']);
        $response->assertResponseStatus(422);

        // Test title too long
        $response = $this->put("/tasks/{$task->id}", ['title' => str_repeat('A', 256)]);
        $response->assertResponseStatus(422);

        // Test title with invalid characters
        $response = $this->put("/tasks/{$task->id}", ['title' => 'Test<script>alert("xss")</script>']);
        $response->assertResponseStatus(422);
    }

    /**
     * Test validation error for invalid status
     */
    public function testValidationErrorForInvalidStatus()
    {
        $task = Task::create([
            'title' => 'Test Task',
            'status' => 'pending',
            'created_by' => 1
        ]);

        $response = $this->put("/tasks/{$task->id}", ['status' => 'invalid_status']);
        
        $response->assertResponseStatus(422);
        $response->seeJson(['success' => false]);
        $response->seeJsonContains(['status']);
    }

    /**
     * Test status transition validation
     */
    public function testStatusTransitionValidation()
    {
        $task = Task::create([
            'title' => 'Test Task',
            'status' => 'completed',
            'created_by' => 1,
            'completed_at' => now()
        ]);

        // Invalid transition: completed -> pending should fail
        $response = $this->put("/tasks/{$task->id}", ['status' => 'pending']);
        $response->assertResponseStatus(422);

        // Valid transition: completed -> in_progress should succeed
        $response = $this->put("/tasks/{$task->id}", ['status' => 'in_progress']);
        $response->assertResponseStatus(200);
    }

    /**
     * Test date validation
     */
    public function testDateValidation()
    {
        $task = Task::create([
            'title' => 'Test Task',
            'status' => 'pending',
            'created_by' => 1
        ]);

        // Test due_date in the past
        $response = $this->put("/tasks/{$task->id}", ['due_date' => '2023-01-01']);
        $response->assertResponseStatus(422);

        // Test due_date too far in future
        $response = $this->put("/tasks/{$task->id}", ['due_date' => '2040-01-01']);
        $response->assertResponseStatus(422);

        // Test completed_at in future
        $response = $this->put("/tasks/{$task->id}", ['completed_at' => '2030-01-01']);
        $response->assertResponseStatus(422);

        // Test valid future due_date
        $futureDate = now()->addDays(7)->toDateString();
        $response = $this->put("/tasks/{$task->id}", ['due_date' => $futureDate]);
        $response->assertResponseStatus(200);
    }

    /**
     * Test completion logic validation
     */
    public function testCompletionLogicValidation()
    {
        $task = Task::create([
            'title' => 'Test Task',
            'status' => 'pending',
            'created_by' => 1
        ]);

        // Test status completed without completed_at should auto-set completed_at
        $response = $this->put("/tasks/{$task->id}", ['status' => 'completed']);
        $response->assertResponseStatus(200);
        
        $updatedTask = Task::find($task->id);
        $this->assertNotNull($updatedTask->completed_at);

        // Test status change from completed should clear completed_at
        $response = $this->put("/tasks/{$task->id}", ['status' => 'in_progress']);
        $response->assertResponseStatus(200);
        
        $updatedTask = Task::find($task->id);
        $this->assertNull($updatedTask->completed_at);
    }

    /**
     * Test user ID validation
     */
    public function testUserIdValidation()
    {
        $task = Task::create([
            'title' => 'Test Task',
            'status' => 'pending',
            'created_by' => 1
        ]);

        // Test negative user ID
        $response = $this->put("/tasks/{$task->id}", ['assigned_to' => -1]);
        $response->assertResponseStatus(422);

        // Test user ID too large
        $response = $this->put("/tasks/{$task->id}", ['assigned_to' => 1000000]);
        $response->assertResponseStatus(422);

        // Test valid user ID
        $response = $this->put("/tasks/{$task->id}", ['assigned_to' => 123]);
        $response->assertResponseStatus(200);
    }

    /**
     * Test partial update functionality
     */
    public function testPartialUpdateFunctionality()
    {
        $task = Task::create([
            'title' => 'Original Title',
            'description' => 'Original Description',
            'status' => 'pending',
            'created_by' => 1,
            'assigned_to' => 1
        ]);

        // Update only title
        $response = $this->put("/tasks/{$task->id}", ['title' => 'New Title Only']);
        $response->assertResponseStatus(200);
        
        $updatedTask = Task::find($task->id);
        $this->assertEquals('New Title Only', $updatedTask->title);
        $this->assertEquals('Original Description', $updatedTask->description); // Should remain unchanged
        $this->assertEquals('pending', $updatedTask->status); // Should remain unchanged

        // Update only description
        $response = $this->put("/tasks/{$task->id}", ['description' => 'New Description Only']);
        $response->assertResponseStatus(200);
        
        $updatedTask = Task::find($task->id);
        $this->assertEquals('New Title Only', $updatedTask->title); // Should remain from previous update
        $this->assertEquals('New Description Only', $updatedTask->description);
    }

    /**
     * Test field clearing functionality
     */
    public function testFieldClearingFunctionality()
    {
        $task = Task::create([
            'title' => 'Test Task',
            'description' => 'Original Description',
            'status' => 'pending',
            'created_by' => 1,
            'assigned_to' => 123,
            'due_date' => now()->addDays(7)
        ]);

        // Clear optional fields by setting to null
        $response = $this->put("/tasks/{$task->id}", [
            'description' => null,
            'assigned_to' => null,
            'due_date' => null
        ]);
        
        $response->assertResponseStatus(200);
        
        $updatedTask = Task::find($task->id);
        $this->assertNull($updatedTask->description);
        $this->assertNull($updatedTask->assigned_to);
        $this->assertNull($updatedTask->due_date);
    }

    /**
     * Test empty update request
     */
    public function testEmptyUpdateRequest()
    {
        $task = Task::create([
            'title' => 'Test Task',
            'status' => 'pending',
            'created_by' => 1
        ]);

        // Empty update should return success with no changes
        $response = $this->put("/tasks/{$task->id}", []);
        $response->assertResponseStatus(200);
        $response->seeJsonContains(['No valid data provided for update']);
    }

    /**
     * Test task not found validation
     */
    public function testTaskNotFoundValidation()
    {
        $response = $this->put("/tasks/99999", ['title' => 'New Title']);
        $response->assertResponseStatus(404);
        $response->seeJson(['success' => false]);
        $response->seeJsonContains(['Task not found']);
    }

    /**
     * Test invalid task ID validation
     */
    public function testInvalidTaskIdValidation()
    {
        $response = $this->put("/tasks/invalid_id", ['title' => 'New Title']);
        $response->assertResponseStatus(422);
        $response->seeJson(['success' => false]);
    }

    /**
     * Test description length validation
     */
    public function testDescriptionLengthValidation()
    {
        $task = Task::create([
            'title' => 'Test Task',
            'status' => 'pending',
            'created_by' => 1
        ]);

        // Test description too long
        $longDescription = str_repeat('A', 1001);
        $response = $this->put("/tasks/{$task->id}", ['description' => $longDescription]);
        $response->assertResponseStatus(422);

        // Test valid description length
        $validDescription = str_repeat('A', 500);
        $response = $this->put("/tasks/{$task->id}", ['description' => $validDescription]);
        $response->assertResponseStatus(200);
    }

    /**
     * Test input sanitization
     */
    public function testInputSanitization()
    {
        $task = Task::create([
            'title' => 'Test Task',
            'status' => 'pending',
            'created_by' => 1
        ]);

        // Test whitespace trimming
        $response = $this->put("/tasks/{$task->id}", [
            'title' => '  Trimmed Title  ',
            'assigned_to' => '  123  '
        ]);
        
        $response->assertResponseStatus(200);
        
        $updatedTask = Task::find($task->id);
        $this->assertEquals('Trimmed Title', $updatedTask->title);
        $this->assertEquals(123, $updatedTask->assigned_to);
    }
}