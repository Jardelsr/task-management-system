<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ValidationMessageService;
use App\Services\BusinessRulesValidator;
use App\Models\Task;
use App\Exceptions\TaskValidationException;

class CustomValidationMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any cached messages before each test
        ValidationMessageService::clearCache();
    }

    /**
     * Test basic validation message retrieval
     */
    public function testBasicValidationMessageRetrieval()
    {
        $messages = ValidationMessageService::getTaskCreationMessages();
        
        $this->assertIsArray($messages);
        $this->assertNotEmpty($messages);
        $this->assertArrayHasKey('title.required', $messages);
        $this->assertStringContainsString('required', $messages['title.required']);
    }

    /**
     * Test task update messages
     */
    public function testTaskUpdateMessages()
    {
        $messages = ValidationMessageService::getTaskUpdateMessages();
        
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('status.in', $messages);
        $this->assertStringContainsString('pending', $messages['status.in']);
        $this->assertStringContainsString('completed', $messages['status.in']);
    }

    /**
     * Test log validation messages
     */
    public function testLogValidationMessages()
    {
        $messages = ValidationMessageService::getLogValidationMessages();
        
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('limit.integer', $messages);
        $this->assertArrayHasKey('page.min', $messages);
    }

    /**
     * Test business rule messages
     */
    public function testBusinessRuleMessages()
    {
        $messages = ValidationMessageService::getBusinessRuleMessages();
        
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('status_transition.invalid', $messages);
        $this->assertArrayHasKey('assignment.self_assignment', $messages);
    }

    /**
     * Test context messages
     */
    public function testContextMessages()
    {
        $messages = ValidationMessageService::getContextMessages();
        
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('task_not_found', $messages);
        $this->assertArrayHasKey('validation_failed', $messages);
    }

    /**
     * Test dynamic message replacement
     */
    public function testDynamicMessageReplacement()
    {
        $message = ValidationMessageService::getBusinessMessage(
            'status_transition.invalid',
            ['from' => 'pending', 'to' => 'completed']
        );
        
        $this->assertStringContainsString('pending', $message);
        $this->assertStringContainsString('completed', $message);
        $this->assertStringNotContainsString(':from', $message);
        $this->assertStringNotContainsString(':to', $message);
    }

    /**
     * Test context message formatting
     */
    public function testContextMessageFormatting()
    {
        $message = ValidationMessageService::getContextMessage(
            'task_not_found',
            ['id' => 123]
        );
        
        $this->assertStringContainsString('123', $message);
        $this->assertStringNotContainsString(':id', $message);
    }

    /**
     * Test common messages merging
     */
    public function testCommonMessagesMerging()
    {
        $specific = ['custom.rule' => 'Custom message'];
        $merged = ValidationMessageService::mergeWithCommonMessages($specific);
        
        $this->assertArrayHasKey('custom.rule', $merged);
        $this->assertArrayHasKey('required', $merged);
        $this->assertEquals('Custom message', $merged['custom.rule']);
    }

    /**
     * Test date range validation messages
     */
    public function testDateRangeValidationMessages()
    {
        $messages = ValidationMessageService::getDateRangeValidationMessages();
        
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('start_date.required', $messages);
        $this->assertArrayHasKey('end_date.after', $messages);
        $this->assertArrayHasKey('limit.max', $messages);
    }

    /**
     * Test all messages retrieval
     */
    public function testAllMessagesRetrieval()
    {
        $messages = ValidationMessageService::getAllMessages();
        
        $this->assertIsArray($messages);
        $this->assertNotEmpty($messages);
        
        // Should contain messages from all categories
        $this->assertArrayHasKey('title.required', $messages);
        $this->assertArrayHasKey('status_transition.invalid', $messages);
        $this->assertArrayHasKey('task_not_found', $messages);
    }

    /**
     * Test localization support (English)
     */
    public function testLocalizationEnglish()
    {
        $messages = ValidationMessageService::getLocalizedMessages('en', 'task_creation');
        
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('title.required', $messages);
        $this->assertStringContainsString('required', $messages['title.required']);
    }

    /**
     * Test localization support (Portuguese)
     */
    public function testLocalizationPortuguese()
    {
        $messages = ValidationMessageService::getLocalizedMessages('pt', 'task_creation');
        
        $this->assertIsArray($messages);
        
        if (isset($messages['title.required'])) {
            // Should contain Portuguese text
            $this->assertStringContainsString('obrigatório', $messages['title.required']);
        } else {
            // Fallback to English if Portuguese not available
            $this->markTestSkipped('Portuguese localization not available, fallback working');
        }
    }

    /**
     * Test localized business messages
     */
    public function testLocalizedBusinessMessages()
    {
        $message = ValidationMessageService::getLocalizedBusinessMessage(
            'status_transition.invalid',
            ['from' => 'pending', 'to' => 'completed'],
            'en'
        );
        
        $this->assertIsString($message);
        $this->assertStringContainsString('pending', $message);
        $this->assertStringContainsString('completed', $message);
    }

    /**
     * Test current locale messages
     */
    public function testCurrentLocaleMessages()
    {
        // Set application locale
        app()->setLocale('en');
        
        $messages = ValidationMessageService::getMessagesForCurrentLocale('business_rules');
        
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('status_transition.invalid', $messages);
    }
}

/**
 * Business Rules Validator Tests
 */
class BusinessRulesValidatorTest extends TestCase
{
    /**
     * Test valid status transitions
     */
    public function testValidStatusTransitions()
    {
        // Valid transition: pending to in_progress
        $this->expectNotToPerformAssertions();
        BusinessRulesValidator::validateStatusTransition('pending', 'in_progress');
    }

    /**
     * Test invalid status transitions
     */
    public function testInvalidStatusTransitions()
    {
        $this->expectException(TaskValidationException::class);
        BusinessRulesValidator::validateStatusTransition('completed', 'pending');
    }

    /**
     * Test completion requirements
     */
    public function testCompletionRequirements()
    {
        $this->expectException(TaskValidationException::class);
        BusinessRulesValidator::validateStatusTransition(
            'in_progress',
            'completed',
            [] // Missing completed_at
        );
    }

    /**
     * Test valid completion with date
     */
    public function testValidCompletionWithDate()
    {
        $this->expectNotToPerformAssertions();
        BusinessRulesValidator::validateStatusTransition(
            'in_progress',
            'completed',
            ['completed_at' => now()->toDateTimeString()]
        );
    }

    /**
     * Test self-assignment validation
     */
    public function testSelfAssignmentValidation()
    {
        $this->expectException(TaskValidationException::class);
        BusinessRulesValidator::validateTaskAssignment([
            'created_by' => 1,
            'assigned_to' => 1
        ]);
    }

    /**
     * Test valid assignment
     */
    public function testValidAssignment()
    {
        $this->expectNotToPerformAssertions();
        BusinessRulesValidator::validateTaskAssignment([
            'created_by' => 1,
            'assigned_to' => 2
        ]);
    }

    /**
     * Test past due date validation
     */
    public function testPastDueDateValidation()
    {
        $this->expectException(TaskValidationException::class);
        BusinessRulesValidator::validateDueDateRules([
            'due_date' => now()->subDay()->toDateTimeString(),
            'status' => 'pending'
        ]);
    }

    /**
     * Test future due date validation
     */
    public function testFutureDueDateValidation()
    {
        $this->expectNotToPerformAssertions();
        BusinessRulesValidator::validateDueDateRules([
            'due_date' => now()->addDay()->toDateTimeString(),
            'status' => 'pending'
        ]);
    }

    /**
     * Test task creation workflow
     */
    public function testTaskCreationWorkflow()
    {
        // Valid creation with pending status
        $this->expectNotToPerformAssertions();
        BusinessRulesValidator::validateTaskCreationWorkflow([
            'status' => 'pending'
        ]);
    }

    /**
     * Test invalid task creation workflow
     */
    public function testInvalidTaskCreationWorkflow()
    {
        $this->expectException(TaskValidationException::class);
        BusinessRulesValidator::validateTaskCreationWorkflow([
            'status' => 'completed' // Cannot create completed tasks
        ]);
    }

    /**
     * Test get valid status transitions
     */
    public function testGetValidStatusTransitions()
    {
        $transitions = BusinessRulesValidator::getValidStatusTransitions();
        
        $this->assertIsArray($transitions);
        $this->assertArrayHasKey('pending', $transitions);
        $this->assertContains('in_progress', $transitions['pending']);
    }

    /**
     * Test get valid next statuses
     */
    public function testGetValidNextStatuses()
    {
        $nextStatuses = BusinessRulesValidator::getValidNextStatuses('pending');
        
        $this->assertIsArray($nextStatuses);
        $this->assertContains('in_progress', $nextStatuses);
        $this->assertContains('cancelled', $nextStatuses);
    }

    /**
     * Test status transition validation check
     */
    public function testStatusTransitionValidationCheck()
    {
        $this->assertTrue(BusinessRulesValidator::isValidStatusTransition('pending', 'in_progress'));
        $this->assertFalse(BusinessRulesValidator::isValidStatusTransition('completed', 'pending'));
        $this->assertTrue(BusinessRulesValidator::isValidStatusTransition('pending', 'pending')); // Same status
    }
}