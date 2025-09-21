<?php

namespace Tests\Feature;

use Laravel\Lumen\Testing\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Response;

/**
 * Feature test for UpdateTaskRequest validation in HTTP context
 * 
 * This test suite validates UpdateTaskRequest rules through actual HTTP requests
 * to ensure validation works correctly in the full application context.
 */
class UpdateTaskRequestFeatureTest extends TestCase
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
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2025-09-21 10:00:00'); // Set consistent test date
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset Carbon test time
        parent::tearDown();
    }

    /**
     * Create a test task for update testing
     */
    private function createTestTask(array $attributes = []): Task
    {
        return Task::create(array_merge([
            'title' => 'Test Task',
            'description' => 'Test Description',
            'status' => 'pending',
            'created_by' => 1,
            'assigned_to' => 1
        ], $attributes));
    }

    /**
     * Test successful task update with valid data
     */
    public function testSuccessfulTaskUpdateWithValidData()
    {
        $task = $this->createTestTask();

        $updateData = [
            'title' => 'Updated Task Title',
            'description' => 'Updated description with more details',
            'status' => 'in_progress',
            'assigned_to' => 2,
            'due_date' => '2025-12-31'
        ];

        $response = $this->put("/tasks/{$task->id}", $updateData);

        $response->assertResponseStatus(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'title',
                'description',
                'status',
                'assigned_to',
                'due_date'
            ]
        ]);

        // Verify the task was actually updated
        $updatedTask = Task::find($task->id);
        $this->assertEquals('Updated Task Title', $updatedTask->title);
        $this->assertEquals('in_progress', $updatedTask->status);
        $this->assertEquals(2, $updatedTask->assigned_to);
    }

    /**
     * Test title validation through HTTP requests
     *
     * @dataProvider titleValidationHttpProvider
     */
    public function testTitleValidationThroughHttp($title, $expectedStatus, $expectedErrorMessages = [])
    {
        $task = $this->createTestTask();

        $updateData = ['title' => $title];
        $response = $this->put("/tasks/{$task->id}", $updateData);

        $response->assertResponseStatus($expectedStatus);

        if ($expectedStatus === Response::HTTP_UNPROCESSABLE_ENTITY) {
            $response->seeJsonStructure(['errors' => ['title']]);
            
            foreach ($expectedErrorMessages as $expectedMessage) {
                $response->seeJson(['title' => [$expectedMessage]]);
            }
        }
    }

    /**
     * Data provider for HTTP title validation tests
     */
    public function titleValidationHttpProvider()
    {
        return [
            // Valid titles
            ['Valid Title', Response::HTTP_OK],
            ['Task with Numbers 123', Response::HTTP_OK],
            ['Title-with_punctuation!', Response::HTTP_OK],
            
            // Invalid titles
            ['AB', Response::HTTP_UNPROCESSABLE_ENTITY, ['The task title must be at least 3 characters.']],
            [str_repeat('A', 256), Response::HTTP_UNPROCESSABLE_ENTITY, ['The task title may not be greater than 255 characters.']],
            ['Title with <script>alert("xss")</script>', Response::HTTP_UNPROCESSABLE_ENTITY, ['The task title contains invalid characters.']],
        ];
    }

    /**
     * Test status validation through HTTP requests
     *
     * @dataProvider statusValidationHttpProvider
     */
    public function testStatusValidationThroughHttp($status, $expectedStatus, $expectedErrorMessages = [])
    {
        $task = $this->createTestTask();

        $updateData = ['status' => $status];
        $response = $this->put("/tasks/{$task->id}", $updateData);

        $response->assertResponseStatus($expectedStatus);

        if ($expectedStatus === Response::HTTP_UNPROCESSABLE_ENTITY) {
            $response->seeJsonStructure(['errors' => ['status']]);
            
            foreach ($expectedErrorMessages as $expectedMessage) {
                $response->seeJson(['status' => [$expectedMessage]]);
            }
        }
    }

    /**
     * Data provider for HTTP status validation tests
     */
    public function statusValidationHttpProvider()
    {
        return [
            // Valid statuses
            ['pending', Response::HTTP_OK],
            ['in_progress', Response::HTTP_OK],
            ['completed', Response::HTTP_OK],
            ['cancelled', Response::HTTP_OK],
            
            // Invalid statuses
            ['invalid_status', Response::HTTP_UNPROCESSABLE_ENTITY, ['The selected status is invalid.']],
            ['PENDING', Response::HTTP_UNPROCESSABLE_ENTITY, ['The selected status is invalid.']],
            ['draft', Response::HTTP_UNPROCESSABLE_ENTITY, ['The selected status is invalid.']],
        ];
    }

    /**
     * Test due_date validation through HTTP requests
     *
     * @dataProvider dueDateValidationHttpProvider
     */
    public function testDueDateValidationThroughHttp($dueDate, $expectedStatus, $expectedErrorMessages = [])
    {
        $task = $this->createTestTask();

        $updateData = ['due_date' => $dueDate];
        $response = $this->put("/tasks/{$task->id}", $updateData);

        $response->assertResponseStatus($expectedStatus);

        if ($expectedStatus === Response::HTTP_UNPROCESSABLE_ENTITY) {
            $response->seeJsonStructure(['errors' => ['due_date']]);
            
            foreach ($expectedErrorMessages as $expectedMessage) {
                $response->seeJson(['due_date' => [$expectedMessage]]);
            }
        }
    }

    /**
     * Data provider for HTTP due_date validation tests
     */
    public function dueDateValidationHttpProvider()
    {
        return [
            // Valid dates
            ['2025-09-22', Response::HTTP_OK], // Tomorrow
            ['2025-12-31', Response::HTTP_OK], // End of year
            ['2030-01-01', Response::HTTP_OK], // Future year
            [null, Response::HTTP_OK], // Nullable
            
            // Invalid dates
            ['2025-09-21', Response::HTTP_UNPROCESSABLE_ENTITY, ['The due date must be a date after now.']],
            ['2025-09-20', Response::HTTP_UNPROCESSABLE_ENTITY, ['The due date must be a date after now.']],
            ['2036-01-01', Response::HTTP_UNPROCESSABLE_ENTITY, ['The due date must be within the next 10 years.']],
            ['invalid-date', Response::HTTP_UNPROCESSABLE_ENTITY, ['The due date must be a valid date.']],
        ];
    }

    /**
     * Test completed_at validation through HTTP requests
     *
     * @dataProvider completedAtValidationHttpProvider
     */
    public function testCompletedAtValidationThroughHttp($completedAt, $expectedStatus, $expectedErrorMessages = [])
    {
        $task = $this->createTestTask();

        $updateData = ['completed_at' => $completedAt];
        $response = $this->put("/tasks/{$task->id}", $updateData);

        $response->assertResponseStatus($expectedStatus);

        if ($expectedStatus === Response::HTTP_UNPROCESSABLE_ENTITY) {
            $response->seeJsonStructure(['errors' => ['completed_at']]);
            
            foreach ($expectedErrorMessages as $expectedMessage) {
                $response->seeJson(['completed_at' => [$expectedMessage]]);
            }
        }
    }

    /**
     * Data provider for HTTP completed_at validation tests
     */
    public function completedAtValidationHttpProvider()
    {
        return [
            // Valid dates
            ['2025-09-21 09:00:00', Response::HTTP_OK], // Earlier today
            ['2025-09-20', Response::HTTP_OK], // Yesterday
            [null, Response::HTTP_OK], // Nullable
            
            // Invalid dates
            ['2025-09-21 11:00:00', Response::HTTP_UNPROCESSABLE_ENTITY, ['The completed at date cannot be in the future.']],
            ['2025-09-22', Response::HTTP_UNPROCESSABLE_ENTITY, ['The completed at date cannot be in the future.']],
            ['invalid-date', Response::HTTP_UNPROCESSABLE_ENTITY, ['The completed at field must be a valid date.']],
        ];
    }

    /**
     * Test integer field validation through HTTP requests
     *
     * @dataProvider integerFieldValidationHttpProvider
     */
    public function testIntegerFieldValidationThroughHttp($field, $value, $expectedStatus, $expectedErrorMessages = [])
    {
        $task = $this->createTestTask();

        $updateData = [$field => $value];
        $response = $this->put("/tasks/{$task->id}", $updateData);

        $response->assertResponseStatus($expectedStatus);

        if ($expectedStatus === Response::HTTP_UNPROCESSABLE_ENTITY) {
            $response->seeJsonStructure(['errors' => [$field]]);
            
            foreach ($expectedErrorMessages as $expectedMessage) {
                $response->seeJson([$field => [$expectedMessage]]);
            }
        }
    }

    /**
     * Data provider for HTTP integer field validation tests
     */
    public function integerFieldValidationHttpProvider()
    {
        $testCases = [];
        $fields = ['assigned_to', 'created_by'];
        
        foreach ($fields as $field) {
            $testCases = array_merge($testCases, [
                // Valid values
                [$field, 1, Response::HTTP_OK],
                [$field, 100, Response::HTTP_OK],
                [$field, 999999, Response::HTTP_OK],
                [$field, null, Response::HTTP_OK],
                
                // Invalid values
                [$field, 0, Response::HTTP_UNPROCESSABLE_ENTITY, ["The {$field} field must be at least 1."]],
                [$field, -1, Response::HTTP_UNPROCESSABLE_ENTITY, ["The {$field} field must be at least 1."]],
                [$field, 1000000, Response::HTTP_UNPROCESSABLE_ENTITY, ["The {$field} field must not exceed 999999."]],
                [$field, 'not_integer', Response::HTTP_UNPROCESSABLE_ENTITY, ["The {$field} field must be a valid integer."]],
            ]);
        }
        
        return $testCases;
    }

    /**
     * Test partial update functionality
     */
    public function testPartialUpdateFunctionality()
    {
        $task = $this->createTestTask([
            'title' => 'Original Title',
            'description' => 'Original Description',
            'status' => 'pending'
        ]);

        // Update only title
        $response = $this->put("/tasks/{$task->id}", ['title' => 'Updated Title Only']);
        $response->assertResponseStatus(200);

        $updatedTask = Task::find($task->id);
        $this->assertEquals('Updated Title Only', $updatedTask->title);
        $this->assertEquals('Original Description', $updatedTask->description);
        $this->assertEquals('pending', $updatedTask->status);

        // Update only status
        $response = $this->put("/tasks/{$task->id}", ['status' => 'in_progress']);
        $response->assertResponseStatus(200);

        $updatedTask = Task::find($task->id);
        $this->assertEquals('Updated Title Only', $updatedTask->title); // Should remain unchanged
        $this->assertEquals('in_progress', $updatedTask->status); // Should be updated
    }

    /**
     * Test status transition validation through HTTP
     */
    public function testStatusTransitionValidationThroughHttp()
    {
        // Create task in completed status
        $task = $this->createTestTask(['status' => 'completed']);

        // Try invalid transition: completed -> pending
        $response = $this->put("/tasks/{$task->id}", ['status' => 'pending']);
        $response->assertResponseStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->seeJson(['errors' => ['status' => ["Cannot transition from 'completed' to 'pending'"]]]);

        // Try valid transition: completed -> in_progress
        $response = $this->put("/tasks/{$task->id}", ['status' => 'in_progress']);
        $response->assertResponseStatus(Response::HTTP_OK);
        
        $updatedTask = Task::find($task->id);
        $this->assertEquals('in_progress', $updatedTask->status);
    }

    /**
     * Test status consistency validation through HTTP
     */
    public function testStatusConsistencyValidationThroughHttp()
    {
        $task = $this->createTestTask();

        // Test: Set status to completed without completed_at
        $response = $this->put("/tasks/{$task->id}", [
            'status' => 'completed',
            'completed_at' => null
        ]);
        $response->assertResponseStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->seeJson(['errors' => ['completed_at' => ['Completed at date is required when status is completed']]]);

        // Test: Set status to completed with valid completed_at
        $response = $this->put("/tasks/{$task->id}", [
            'status' => 'completed',
            'completed_at' => '2025-09-21 09:00:00'
        ]);
        $response->assertResponseStatus(Response::HTTP_OK);

        // Test: Set status to in_progress with completed_at (should fail)
        $response = $this->put("/tasks/{$task->id}", [
            'status' => 'in_progress',
            'completed_at' => '2025-09-21 09:00:00'
        ]);
        $response->assertResponseStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->seeJson(['errors' => ['completed_at' => ['Completed at date should only be set when status is completed']]]);
    }

    /**
     * Test multiple field validation errors
     */
    public function testMultipleFieldValidationErrors()
    {
        $task = $this->createTestTask();

        $invalidData = [
            'title' => 'AB', // Too short
            'status' => 'invalid_status', // Invalid status
            'assigned_to' => -1, // Invalid integer
            'due_date' => '2020-01-01', // Past date
            'completed_at' => '2030-01-01' // Future date
        ];

        $response = $this->put("/tasks/{$task->id}", $invalidData);
        $response->assertResponseStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Should have errors for all invalid fields
        $response->seeJsonStructure([
            'errors' => [
                'title',
                'status',
                'assigned_to',
                'due_date',
                'completed_at'
            ]
        ]);
    }

    /**
     * Test edge case: empty update data
     */
    public function testEmptyUpdateData()
    {
        $task = $this->createTestTask();

        $response = $this->put("/tasks/{$task->id}", []);
        
        // Empty update should succeed (no fields to validate)
        $response->assertResponseStatus(Response::HTTP_OK);

        // Task should remain unchanged
        $unchangedTask = Task::find($task->id);
        $this->assertEquals($task->title, $unchangedTask->title);
        $this->assertEquals($task->status, $unchangedTask->status);
    }

    /**
     * Test nonexistent task update
     */
    public function testNonexistentTaskUpdate()
    {
        $response = $this->put("/tasks/99999", ['title' => 'Updated Title']);
        $response->assertResponseStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * Test task update with mixed valid and invalid fields
     */
    public function testMixedValidInvalidFieldsUpdate()
    {
        $task = $this->createTestTask();

        $mixedData = [
            'title' => 'Valid Updated Title', // Valid
            'status' => 'invalid_status', // Invalid
            'description' => 'Valid updated description' // Valid
        ];

        $response = $this->put("/tasks/{$task->id}", $mixedData);
        $response->assertResponseStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Should only have error for invalid field
        $response->seeJsonStructure(['errors' => ['status']]);
        $response->dontSeeJson(['errors' => ['title']]);
        $response->dontSeeJson(['errors' => ['description']]);

        // Task should not be updated when validation fails
        $unchangedTask = Task::find($task->id);
        $this->assertNotEquals('Valid Updated Title', $unchangedTask->title);
        $this->assertNotEquals('Valid updated description', $unchangedTask->description);
    }

    /**
     * Test description validation edge cases
     */
    public function testDescriptionValidationEdgeCases()
    {
        $task = $this->createTestTask();

        // Test max length description (should pass)
        $validDescription = str_repeat('A', 1000);
        $response = $this->put("/tasks/{$task->id}", ['description' => $validDescription]);
        $response->assertResponseStatus(Response::HTTP_OK);

        // Test over max length description (should fail)
        $invalidDescription = str_repeat('A', 1001);
        $response = $this->put("/tasks/{$task->id}", ['description' => $invalidDescription]);
        $response->assertResponseStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->seeJson(['errors' => ['description' => ['The description may not be greater than 1000 characters.']]]);

        // Test empty description (should pass)
        $response = $this->put("/tasks/{$task->id}", ['description' => '']);
        $response->assertResponseStatus(Response::HTTP_OK);

        // Test null description (should pass)
        $response = $this->put("/tasks/{$task->id}", ['description' => null]);
        $response->assertResponseStatus(Response::HTTP_OK);
    }

    /**
     * Test complex real-world update scenarios
     */
    public function testComplexRealWorldScenarios()
    {
        $task = $this->createTestTask([
            'title' => 'Project Setup',
            'description' => 'Initial project setup task',
            'status' => 'pending'
        ]);

        // Scenario 1: Start working on a task
        $response = $this->put("/tasks/{$task->id}", [
            'status' => 'in_progress',
            'assigned_to' => 123
        ]);
        $response->assertResponseStatus(Response::HTTP_OK);

        // Scenario 2: Complete the task
        $response = $this->put("/tasks/{$task->id}", [
            'title' => 'Project Setup - Completed',
            'status' => 'completed',
            'completed_at' => '2025-09-21 09:30:00',
            'description' => 'Project setup completed successfully with all configurations'
        ]);
        $response->assertResponseStatus(Response::HTTP_OK);

        // Verify final state
        $completedTask = Task::find($task->id);
        $this->assertEquals('Project Setup - Completed', $completedTask->title);
        $this->assertEquals('completed', $completedTask->status);
        $this->assertNotNull($completedTask->completed_at);
        $this->assertEquals(123, $completedTask->assigned_to);
    }
}