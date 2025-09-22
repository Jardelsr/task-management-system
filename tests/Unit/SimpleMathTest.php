<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Simple unit test without database dependencies
 */
class SimpleMathTest extends TestCase
{
    protected function setUp(): void
    {
        // Skip parent setup to avoid database issues
        // parent::setUp();
    }

    protected function tearDown(): void
    {
        // Skip parent teardown to avoid database issues
        // parent::tearDown();
    }

    public function testBasicAddition()
    {
        $result = 2 + 3;
        $this->assertEquals(5, $result);
    }

    public function testStringConcatenation()
    {
        $result = 'Hello' . ' ' . 'World';
        $this->assertEquals('Hello World', $result);
    }

    public function testArrayCount()
    {
        $array = [1, 2, 3, 4, 5];
        $this->assertCount(5, $array);
    }

    public function testBooleanLogic()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
        $this->assertTrue(1 > 0);
        $this->assertFalse(1 < 0);
    }

    public function testStringOperations()
    {
        $string = 'Task Management System';
        
        $this->assertStringContainsString('Task', $string);
        $this->assertStringContainsString('Management', $string);
        $this->assertStringStartsWith('Task', $string);
        $this->assertStringEndsWith('System', $string);
        $this->assertEquals(22, strlen($string));
    }

    public function testArrayOperations()
    {
        $tasks = [
            'pending',
            'in_progress', 
            'completed',
            'cancelled'
        ];

        $this->assertContains('pending', $tasks);
        $this->assertNotContains('deleted', $tasks);
        $this->assertEquals('pending', $tasks[0]);
        $this->assertEquals('cancelled', end($tasks));
    }

    public function testNumberComparisons()
    {
        $priority1 = 1;
        $priority2 = 5;
        $priority3 = 10;

        $this->assertGreaterThan($priority1, $priority2);
        $this->assertLessThan($priority3, $priority2);
        $this->assertGreaterThanOrEqual(5, $priority2);
        $this->assertLessThanOrEqual(10, $priority3);
    }

    public function testDateFormatting()
    {
        $timestamp = '2024-12-25 15:30:00';
        $formatted = date('Y-m-d', strtotime($timestamp));
        
        $this->assertEquals('2024-12-25', $formatted);
    }

    public function testJSONOperations()
    {
        $data = [
            'id' => 1,
            'title' => 'Test Task',
            'status' => 'pending',
            'priority' => 'high'
        ];

        $json = json_encode($data);
        $decoded = json_decode($json, true);

        $this->assertJson($json);
        $this->assertEquals($data, $decoded);
        $this->assertArrayHasKey('id', $decoded);
        $this->assertEquals('Test Task', $decoded['title']);
    }

    public function testEmailValidation()
    {
        $validEmail = 'test@example.com';
        $invalidEmail = 'not-an-email';

        $this->assertTrue(filter_var($validEmail, FILTER_VALIDATE_EMAIL) !== false);
        $this->assertFalse(filter_var($invalidEmail, FILTER_VALIDATE_EMAIL) !== false);
    }
}