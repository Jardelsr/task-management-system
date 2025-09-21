<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\ValidationHelper;
use App\Models\Task;
use App\Exceptions\TaskValidationException;

class ValidationTest extends TestCase
{
    /**
     * Test basic update validation rules
     */
    public function testUpdateValidationRules()
    {
        $rules = UpdateTaskRequest::getValidationRules();

        // Check that all expected fields have rules
        $expectedFields = ['title', 'description', 'status', 'assigned_to', 'created_by', 'due_date', 'completed_at'];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $rules, "Missing validation rule for field: {$field}");
        }

        // Check title rules include minimum length
        $this->assertStringContainsString('min:3', implode('|', (array)$rules['title']));
        $this->assertStringContainsString('max:255', implode('|', (array)$rules['title']));
    }

    /**
     * Test partial update rules generation
     */
    public function testPartialUpdateRules()
    {
        $data = [
            'title' => 'New Title',
            'status' => 'completed'
        ];

        $partialRules = UpdateTaskRequest::getPartialUpdateRules($data);

        // Should only include rules for provided fields
        $this->assertArrayHasKey('title', $partialRules);
        $this->assertArrayHasKey('status', $partialRules);
        $this->assertArrayNotHasKey('description', $partialRules);
        $this->assertArrayNotHasKey('assigned_to', $partialRules);
    }

    /**
     * Test status transition validation
     */
    public function testStatusTransitionValidation()
    {
        // Valid transitions
        $validTransitions = [
            ['pending', 'in_progress'],
            ['pending', 'completed'],
            ['in_progress', 'completed'],
            ['completed', 'in_progress'], // Can reopen
            ['cancelled', 'pending'] // Can reactivate
        ];

        foreach ($validTransitions as [$from, $to]) {
            $errors = ValidationHelper::validateStatusTransition($from, $to);
            $this->assertEmpty($errors, "Transition from {$from} to {$to} should be valid");
        }

        // Invalid transitions
        $invalidTransitions = [
            ['completed', 'pending'], // Cannot go back to pending from completed
            ['completed', 'cancelled'], // Cannot cancel completed task
        ];

        foreach ($invalidTransitions as [$from, $to]) {
            $errors = ValidationHelper::validateStatusTransition($from, $to);
            $this->assertNotEmpty($errors, "Transition from {$from} to {$to} should be invalid");
            $this->assertArrayHasKey('status', $errors);
        }
    }

    /**
     * Test completion logic validation
     */
    public function testCompletionLogicValidation()
    {
        // Mock task object
        $task = new class {
            public $status = 'pending';
            public $completed_at = null;
        };

        // Test: Status completed requires completed_at
        $data = ['status' => 'completed'];
        $errors = ValidationHelper::validateCompletionLogic($data, $task);
        $this->assertArrayHasKey('completed_at', $errors);

        // Test: Non-completed status should not have completed_at
        $task->completed_at = '2025-09-20 10:00:00';
        $data = ['status' => 'in_progress'];
        $errors = ValidationHelper::validateCompletionLogic($data, $task);
        $this->assertArrayHasKey('completed_at', $errors);
    }

    /**
     * Test input sanitization
     */
    public function testInputSanitization()
    {
        $inputData = [
            'title' => '  Test Title  ', // Extra whitespace
            'description' => '', // Empty string should become null
            'assigned_to' => '123', // String number should become int
            'status' => 'pending'
        ];

        $sanitized = ValidationHelper::sanitizeInput($inputData);

        $this->assertEquals('Test Title', $sanitized['title']);
        $this->assertNull($sanitized['description']);
        $this->assertEquals(123, $sanitized['assigned_to']);
        $this->assertIsInt($sanitized['assigned_to']);
    }

    /**
     * Test field validation with invalid data
     */
    public function testFieldValidationErrors()
    {
        // Test title validation
        $invalidTitles = [
            '' => 'Title cannot be empty',
            'AB' => 'Title must be at least 3 characters',
            str_repeat('A', 256) => 'Title cannot exceed 255 characters',
            'Test<script>' => 'Title contains invalid characters'
        ];

        foreach ($invalidTitles as $title => $expectedError) {
            $errors = ValidationHelper::validateUpdateFields(['title' => $title]);
            $this->assertArrayHasKey('title', $errors);
            $this->assertStringContainsString(
                explode(' ', $expectedError)[0], 
                $errors['title'][0]
            );
        }

        // Test description validation
        $longDescription = str_repeat('A', 1001);
        $errors = ValidationHelper::validateUpdateFields(['description' => $longDescription]);
        $this->assertArrayHasKey('description', $errors);

        // Test invalid status
        $errors = ValidationHelper::validateUpdateFields(['status' => 'invalid_status']);
        $this->assertArrayHasKey('status', $errors);

        // Test invalid user ID
        $errors = ValidationHelper::validateUpdateFields(['assigned_to' => -1]);
        $this->assertArrayHasKey('assigned_to', $errors);

        $errors = ValidationHelper::validateUpdateFields(['assigned_to' => 1000000]);
        $this->assertArrayHasKey('assigned_to', $errors);
    }

    /**
     * Test date validation
     */
    public function testDateValidation()
    {
        // Test due_date in the past
        $errors = ValidationHelper::validateUpdateFields([
            'due_date' => '2023-01-01'
        ]);
        $this->assertArrayHasKey('due_date', $errors);

        // Test due_date too far in future
        $errors = ValidationHelper::validateUpdateFields([
            'due_date' => '2040-01-01'
        ]);
        $this->assertArrayHasKey('due_date', $errors);

        // Test completed_at in future
        $errors = ValidationHelper::validateUpdateFields([
            'completed_at' => '2030-01-01'
        ]);
        $this->assertArrayHasKey('completed_at', $errors);

        // Test invalid date format
        $errors = ValidationHelper::validateUpdateFields([
            'due_date' => 'invalid-date'
        ]);
        $this->assertArrayHasKey('due_date', $errors);
    }

    /**
     * Test allowed fields filtering
     */
    public function testAllowedFieldsFiltering()
    {
        $inputData = [
            'title' => 'Valid Title',
            'description' => 'Valid Description',
            'status' => 'pending',
            'invalid_field' => 'Should be removed',
            'another_invalid' => 'Should also be removed',
            'assigned_to' => 123
        ];

        $allowed = ValidationHelper::getAllowedUpdateFields($inputData);

        $this->assertArrayHasKey('title', $allowed);
        $this->assertArrayHasKey('description', $allowed);
        $this->assertArrayHasKey('status', $allowed);
        $this->assertArrayHasKey('assigned_to', $allowed);
        $this->assertArrayNotHasKey('invalid_field', $allowed);
        $this->assertArrayNotHasKey('another_invalid', $allowed);
    }

    /**
     * Test comprehensive validation and preparation
     */
    public function testValidateAndPrepareUpdateData()
    {
        // Mock existing task
        $task = new class {
            public $status = 'pending';
            public $completed_at = null;
        };

        // Valid data
        $validData = [
            'title' => 'Updated Title',
            'status' => 'in_progress',
            'assigned_to' => 123
        ];

        try {
            $result = ValidationHelper::validateAndPrepareUpdateData($validData, $task);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('title', $result);
            $this->assertArrayHasKey('status', $result);
            $this->assertArrayHasKey('assigned_to', $result);
        } catch (TaskValidationException $e) {
            $this->fail('Valid data should not throw validation exception');
        }

        // Invalid data should throw exception
        $invalidData = [
            'title' => '', // Empty title
            'status' => 'invalid_status'
        ];

        $this->expectException(TaskValidationException::class);
        ValidationHelper::validateAndPrepareUpdateData($invalidData, $task);
    }

    /**
     * Test validation messages
     */
    public function testValidationMessages()
    {
        $messages = UpdateTaskRequest::getValidationMessages();

        // Check that messages exist for key fields
        $expectedMessages = [
            'title.required',
            'title.min',
            'title.max',
            'title.regex',
            'status.in',
            'due_date.after',
            'completed_at.before_or_equal'
        ];

        foreach ($expectedMessages as $key) {
            $this->assertArrayHasKey($key, $messages, "Missing validation message for: {$key}");
        }
    }

    /**
     * Test partial update data filtering
     */
    public function testPartialUpdateFiltering()
    {
        $data = [
            'title' => 'Valid Title',
            'description' => '', // Empty string should be filtered out
            'status' => 'pending',
            'assigned_to' => null, // Explicit null should be kept (for clearing)
            'due_date' => null
        ];

        $filtered = ValidationHelper::filterPartialUpdateData($data);

        $this->assertArrayHasKey('title', $filtered);
        $this->assertArrayNotHasKey('description', $filtered); // Empty string filtered out
        $this->assertArrayHasKey('status', $filtered);
        $this->assertArrayHasKey('assigned_to', $filtered); // Null kept for clearing
        $this->assertArrayHasKey('due_date', $filtered); // Null kept for clearing
    }
}