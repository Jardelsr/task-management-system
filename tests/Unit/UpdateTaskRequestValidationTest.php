<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Comprehensive test suite for UpdateTaskRequest validation rules
 * 
 * This test suite covers all validation rules, edge cases, and error scenarios
 * for the UpdateTaskRequest class including:
 * - Field validation rules (title, description, status, etc.)
 * - Date validation with edge cases
 * - Status transition validation
 * - Field consistency validation
 * - Custom error messages
 * - Partial update scenarios
 */
class UpdateTaskRequestValidationTest extends TestCase
{
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
     * Test title validation rules
     *
     * @dataProvider titleValidationProvider
     */
    public function testTitleValidation($title, $shouldPass, $expectedErrors = [])
    {
        $data = ['title' => $title];
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $messages = UpdateTaskRequest::getValidationMessages();
        
        $validator = Validator::make($data, $rules, $messages);
        
        if ($shouldPass) {
            $this->assertFalse($validator->fails(), 
                "Title validation should pass for: " . var_export($title, true) . 
                ". Errors: " . json_encode($validator->errors()->all())
            );
        } else {
            $this->assertTrue($validator->fails(), 
                "Title validation should fail for: " . var_export($title, true)
            );
            
            foreach ($expectedErrors as $expectedError) {
                $this->assertStringContainsString($expectedError, implode(' ', $validator->errors()->get('title')));
            }
        }
    }

    /**
     * Data provider for title validation tests
     */
    public function titleValidationProvider()
    {
        return [
            // Valid titles
            ['Valid Title', true],
            ['Task with Numbers 123', true],
            ['Title-with_underscores', true],
            ['Title with punctuation!?', true],
            ['Project Planning (Phase 1)', true],
            ['Meeting Notes - Q4 2024', true],
            ['Task with émojis and ñ characters', true],
            [str_repeat('A', 255), true], // Max length
            ['ABC', true], // Min length
            
            // Invalid titles
            [null, false, ['required']],
            ['', false, ['required']],
            ['AB', false, ['at least 3 characters']],
            [str_repeat('A', 256), false, ['may not be greater than 255']],
            [123, false, ['must be a valid string']],
            ['Title with <script>alert("xss")</script>', false, ['invalid characters']],
            ['Title with @#$%^&*+=<>{}[]|\\', false, ['invalid characters']],
            ['Title\nwith\nnewlines', false, ['invalid characters']],
            ['Title\twith\ttabs', false, ['invalid characters']],
        ];
    }

    /**
     * Test description validation rules
     *
     * @dataProvider descriptionValidationProvider
     */
    public function testDescriptionValidation($description, $shouldPass, $expectedErrors = [])
    {
        $data = ['description' => $description];
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $messages = UpdateTaskRequest::getValidationMessages();
        
        $validator = Validator::make($data, $rules, $messages);
        
        if ($shouldPass) {
            $this->assertFalse($validator->fails(), 
                "Description validation should pass. Errors: " . json_encode($validator->errors()->all())
            );
        } else {
            $this->assertTrue($validator->fails(), 
                "Description validation should fail"
            );
            
            foreach ($expectedErrors as $expectedError) {
                $this->assertStringContainsString($expectedError, implode(' ', $validator->errors()->get('description')));
            }
        }
    }

    /**
     * Data provider for description validation tests
     */
    public function descriptionValidationProvider()
    {
        return [
            // Valid descriptions
            [null, true], // Nullable
            ['', true], // Empty string allowed
            ['Simple description', true],
            ['Description with numbers 123 and symbols!', true],
            [str_repeat('A', 1000), true], // Max length
            ['Multi-line\ndescription\nwith\nbreaks', true],
            
            // Invalid descriptions
            [str_repeat('A', 1001), false, ['may not be greater than 1000']],
            [123, false, ['must be a valid string']],
        ];
    }

    /**
     * Test status validation rules
     *
     * @dataProvider statusValidationProvider
     */
    public function testStatusValidation($status, $shouldPass, $expectedErrors = [])
    {
        $data = ['status' => $status];
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $messages = UpdateTaskRequest::getValidationMessages();
        
        $validator = Validator::make($data, $rules, $messages);
        
        if ($shouldPass) {
            $this->assertFalse($validator->fails(), 
                "Status validation should pass for: " . var_export($status, true) . 
                ". Errors: " . json_encode($validator->errors()->all())
            );
        } else {
            $this->assertTrue($validator->fails(), 
                "Status validation should fail for: " . var_export($status, true)
            );
            
            foreach ($expectedErrors as $expectedError) {
                $this->assertStringContainsString($expectedError, implode(' ', $validator->errors()->get('status')));
            }
        }
    }

    /**
     * Data provider for status validation tests
     */
    public function statusValidationProvider()
    {
        return [
            // Valid statuses
            ['pending', true],
            ['in_progress', true],
            ['completed', true],
            ['cancelled', true],
            
            // Invalid statuses
            [null, false, ['required']],
            ['', false, ['required']],
            ['invalid_status', false, ['invalid']],
            ['PENDING', false, ['invalid']], // Case sensitive
            ['draft', false, ['invalid']],
            [123, false, ['must be a valid string']],
        ];
    }

    /**
     * Test assigned_to and created_by field validation
     *
     * @dataProvider integerFieldValidationProvider
     */
    public function testIntegerFieldValidation($field, $value, $shouldPass, $expectedErrors = [])
    {
        $data = [$field => $value];
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $messages = UpdateTaskRequest::getValidationMessages();
        
        $validator = Validator::make($data, $rules, $messages);
        
        if ($shouldPass) {
            $this->assertFalse($validator->fails(), 
                "{$field} validation should pass for: " . var_export($value, true) . 
                ". Errors: " . json_encode($validator->errors()->all())
            );
        } else {
            $this->assertTrue($validator->fails(), 
                "{$field} validation should fail for: " . var_export($value, true)
            );
            
            foreach ($expectedErrors as $expectedError) {
                $this->assertStringContainsString($expectedError, implode(' ', $validator->errors()->get($field)));
            }
        }
    }

    /**
     * Data provider for integer field validation tests
     */
    public function integerFieldValidationProvider()
    {
        $fields = ['assigned_to', 'created_by'];
        $testCases = [];
        
        foreach ($fields as $field) {
            $testCases = array_merge($testCases, [
                // Valid values
                [$field, null, true], // Nullable
                [$field, 1, true], // Min value
                [$field, 100, true], // Regular value
                [$field, 999999, true], // Max value
                
                // Invalid values
                [$field, 0, false, ['must be at least 1']],
                [$field, -1, false, ['must be at least 1']],
                [$field, 1000000, false, ['must not exceed 999999']],
                [$field, 'not_integer', false, ['must be a valid integer']],
                [$field, 1.5, false, ['must be a valid integer']],
            ]);
        }
        
        return $testCases;
    }

    /**
     * Test due_date validation rules
     *
     * @dataProvider dueDateValidationProvider
     */
    public function testDueDateValidation($dueDate, $shouldPass, $expectedErrors = [])
    {
        $data = ['due_date' => $dueDate];
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $messages = UpdateTaskRequest::getValidationMessages();
        
        $validator = Validator::make($data, $rules, $messages);
        
        if ($shouldPass) {
            $this->assertFalse($validator->fails(), 
                "Due date validation should pass for: " . var_export($dueDate, true) . 
                ". Errors: " . json_encode($validator->errors()->all())
            );
        } else {
            $this->assertTrue($validator->fails(), 
                "Due date validation should fail for: " . var_export($dueDate, true)
            );
            
            foreach ($expectedErrors as $expectedError) {
                $this->assertStringContainsString($expectedError, implode(' ', $validator->errors()->get('due_date')));
            }
        }
    }

    /**
     * Data provider for due_date validation tests
     */
    public function dueDateValidationProvider()
    {
        return [
            // Valid dates
            [null, true], // Nullable
            ['2025-09-22', true], // Tomorrow
            ['2025-12-31', true], // End of year
            ['2030-01-01', true], // Future year
            ['2035-09-21 15:30:00', true], // With time, within 10 years
            
            // Invalid dates
            ['2025-09-21', false, ['must be a date after now']], // Today (not after now)
            ['2025-09-20', false, ['must be a date after now']], // Yesterday
            ['2020-01-01', false, ['must be a date after now']], // Past date
            ['2036-01-01', false, ['must be within the next 10 years']], // More than 10 years
            ['invalid-date', false, ['must be a valid date']],
            ['2025-13-01', false, ['must be a valid date']], // Invalid month
            ['2025-02-30', false, ['must be a valid date']], // Invalid day
        ];
    }

    /**
     * Test completed_at validation rules
     *
     * @dataProvider completedAtValidationProvider
     */
    public function testCompletedAtValidation($completedAt, $shouldPass, $expectedErrors = [])
    {
        $data = ['completed_at' => $completedAt];
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $messages = UpdateTaskRequest::getValidationMessages();
        
        $validator = Validator::make($data, $rules, $messages);
        
        if ($shouldPass) {
            $this->assertFalse($validator->fails(), 
                "Completed at validation should pass for: " . var_export($completedAt, true) . 
                ". Errors: " . json_encode($validator->errors()->all())
            );
        } else {
            $this->assertTrue($validator->fails(), 
                "Completed at validation should fail for: " . var_export($completedAt, true)
            );
            
            foreach ($expectedErrors as $expectedError) {
                $this->assertStringContainsString($expectedError, implode(' ', $validator->errors()->get('completed_at')));
            }
        }
    }

    /**
     * Data provider for completed_at validation tests
     */
    public function completedAtValidationProvider()
    {
        return [
            // Valid dates
            [null, true], // Nullable
            ['2025-09-21', true], // Today
            ['2025-09-21 09:59:59', true], // Earlier today
            ['2025-09-20', true], // Yesterday
            ['2025-01-01', true], // Earlier this year
            
            // Invalid dates
            ['2025-09-21 10:00:01', false, ['cannot be in the future']], // Future (after test time)
            ['2025-09-22', false, ['cannot be in the future']], // Tomorrow
            ['2030-01-01', false, ['cannot be in the future']], // Far future
            ['invalid-date', false, ['must be a valid date']],
            ['2025-13-01', false, ['must be a valid date']], // Invalid month
        ];
    }

    /**
     * Test status transition validation rules
     */
    public function testStatusTransitionValidation()
    {
        // Valid transitions
        $validTransitions = [
            ['pending', 'in_progress'],
            ['pending', 'completed'],
            ['pending', 'cancelled'],
            ['in_progress', 'pending'],
            ['in_progress', 'completed'],
            ['in_progress', 'cancelled'],
            ['completed', 'in_progress'], // Can reopen
            ['cancelled', 'pending'], // Can reactivate
            ['cancelled', 'in_progress'], // Can reactivate
        ];

        foreach ($validTransitions as [$currentStatus, $newStatus]) {
            $errors = UpdateTaskRequest::getStatusTransitionRules($currentStatus, $newStatus);
            $this->assertEmpty($errors, 
                "Transition from '{$currentStatus}' to '{$newStatus}' should be valid"
            );
        }

        // Invalid transitions
        $invalidTransitions = [
            ['completed', 'pending'],
            ['completed', 'cancelled'],
        ];

        foreach ($invalidTransitions as [$currentStatus, $newStatus]) {
            $errors = UpdateTaskRequest::getStatusTransitionRules($currentStatus, $newStatus);
            $this->assertNotEmpty($errors, 
                "Transition from '{$currentStatus}' to '{$newStatus}' should be invalid"
            );
            $this->assertArrayHasKey('status', $errors);
        }
    }

    /**
     * Test status consistency validation with completed_at field
     */
    public function testStatusConsistencyValidation()
    {
        // Test: Status completed with null completed_at should fail
        $data = ['status' => 'completed', 'completed_at' => null];
        $errors = UpdateTaskRequest::validateStatusConsistency($data);
        $this->assertArrayHasKey('completed_at', $errors);
        $this->assertStringContainsString('required when status is completed', $errors['completed_at'][0]);

        // Test: Status completed with valid completed_at should pass
        $data = ['status' => 'completed', 'completed_at' => '2025-09-21 09:00:00'];
        $errors = UpdateTaskRequest::validateStatusConsistency($data);
        $this->assertEmpty($errors);

        // Test: Non-completed status with completed_at should fail
        $statuses = ['pending', 'in_progress', 'cancelled'];
        foreach ($statuses as $status) {
            $data = ['status' => $status, 'completed_at' => '2025-09-21 09:00:00'];
            $errors = UpdateTaskRequest::validateStatusConsistency($data);
            $this->assertArrayHasKey('completed_at', $errors);
            $this->assertStringContainsString('should only be set when status is completed', $errors['completed_at'][0]);
        }

        // Test: Non-completed status with null completed_at should pass
        foreach ($statuses as $status) {
            $data = ['status' => $status, 'completed_at' => null];
            $errors = UpdateTaskRequest::validateStatusConsistency($data);
            $this->assertEmpty($errors);
        }
    }

    /**
     * Test partial update rules generation
     */
    public function testPartialUpdateRulesGeneration()
    {
        // Test with single field
        $data = ['title' => 'New Title'];
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayNotHasKey('status', $rules);
        $this->assertArrayNotHasKey('description', $rules);

        // Test with multiple fields
        $data = ['title' => 'New Title', 'status' => 'completed', 'description' => 'New Description'];
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayHasKey('status', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayNotHasKey('assigned_to', $rules);

        // Test status field gets required rule (removes 'sometimes')
        $this->assertContains('required', $rules['status']);
        $this->assertNotContains('sometimes', $rules['status']);
    }

    /**
     * Test validation messages are properly defined
     */
    public function testValidationMessages()
    {
        $messages = UpdateTaskRequest::getValidationMessages();
        
        // Check that all expected message keys are present
        $expectedMessageKeys = [
            'title.required',
            'title.string',
            'title.min',
            'title.max',
            'title.regex',
            'description.string',
            'description.max',
            'status.required',
            'status.string',
            'status.in',
            'assigned_to.integer',
            'assigned_to.min',
            'assigned_to.max',
            'created_by.integer',
            'created_by.min',
            'created_by.max',
            'due_date.date',
            'due_date.after',
            'due_date.before',
            'completed_at.date',
            'completed_at.before_or_equal',
        ];

        foreach ($expectedMessageKeys as $key) {
            $this->assertArrayHasKey($key, $messages, "Missing validation message for key: {$key}");
            $this->assertNotEmpty($messages[$key], "Empty validation message for key: {$key}");
        }

        // Check that status.in message includes available statuses
        $this->assertStringContainsString('pending', $messages['status.in']);
        $this->assertStringContainsString('in_progress', $messages['status.in']);
        $this->assertStringContainsString('completed', $messages['status.in']);
        $this->assertStringContainsString('cancelled', $messages['status.in']);
    }

    /**
     * Test edge case: empty data array
     */
    public function testEmptyDataValidation()
    {
        $data = [];
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $this->assertEmpty($rules, "No rules should be generated for empty data array");
        
        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->fails(), "Empty data should pass validation when no rules are applied");
    }

    /**
     * Test edge case: invalid field names in data
     */
    public function testInvalidFieldNamesInData()
    {
        $data = [
            'invalid_field' => 'value',
            'another_invalid' => 'value',
            'title' => 'Valid Title' // This should still be included
        ];
        
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        
        // Should only include rules for valid fields
        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayNotHasKey('invalid_field', $rules);
        $this->assertArrayNotHasKey('another_invalid', $rules);
    }

    /**
     * Test complex validation scenarios
     */
    public function testComplexValidationScenarios()
    {
        // Scenario 1: Update task to completed status with proper completed_at
        $data = [
            'title' => 'Task Completed Successfully',
            'status' => 'completed',
            'completed_at' => '2025-09-21 09:30:00'
        ];
        
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $validator = Validator::make($data, $rules, UpdateTaskRequest::getValidationMessages());
        $this->assertFalse($validator->fails(), "Complete task update should pass validation");
        
        // Verify consistency
        $consistencyErrors = UpdateTaskRequest::validateStatusConsistency($data);
        $this->assertEmpty($consistencyErrors, "Status consistency should pass");

        // Scenario 2: Invalid title with valid other fields
        $data = [
            'title' => 'AB', // Too short
            'status' => 'in_progress',
            'assigned_to' => 123
        ];
        
        $rules = UpdateTaskRequest::getPartialUpdateRules($data);
        $validator = Validator::make($data, $rules, UpdateTaskRequest::getValidationMessages());
        $this->assertTrue($validator->fails(), "Should fail due to invalid title");
        $this->assertTrue($validator->errors()->has('title'), "Should have title error");
        $this->assertFalse($validator->errors()->has('status'), "Should not have status error");
        $this->assertFalse($validator->errors()->has('assigned_to'), "Should not have assigned_to error");
    }
}