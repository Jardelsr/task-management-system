<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\InputSanitizationService;
use InvalidArgumentException;

class InputSanitizationServiceTest extends TestCase
{
    protected $sanitizationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizationService = new InputSanitizationService();
    }

    public function testSanitizeStringRemovesXSSPatterns()
    {
        $maliciousInput = '<script>alert("XSS")</script>Hello World';
        $result = $this->sanitizationService->sanitizeString($maliciousInput);
        
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Hello World', $result);
    }

    public function testSanitizeStringRemovesEventHandlers()
    {
        $maliciousInput = '<div onclick="alert(\'XSS\')">Click me</div>';
        $result = $this->sanitizationService->sanitizeString($maliciousInput);
        
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('Click me', $result);
    }

    public function testSanitizeStringRemovesJavascriptUrls()
    {
        $maliciousInput = '<a href="javascript:alert(\'XSS\')">Link</a>';
        $result = $this->sanitizationService->sanitizeString($maliciousInput);
        
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testSanitizeEmailValidatesCorrectFormat()
    {
        $validEmail = 'test@example.com';
        $result = $this->sanitizationService->sanitizeEmail($validEmail);
        
        $this->assertEquals($validEmail, $result);
    }

    public function testSanitizeEmailRejectsInvalidFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        
        $invalidEmail = 'not-an-email';
        $this->sanitizationService->sanitizeEmail($invalidEmail);
    }

    public function testSanitizeEmailRemovesDangerousCharacters()
    {
        $maliciousEmail = 'test<script>@example.com';
        
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizationService->sanitizeEmail($maliciousEmail);
    }

    public function testSanitizeIntegerReturnsValidInteger()
    {
        $validInt = '123';
        $result = $this->sanitizationService->sanitizeInteger($validInt);
        
        $this->assertEquals(123, $result);
        $this->assertIsInt($result);
    }

    public function testSanitizeIntegerRejectsNonNumeric()
    {
        $this->expectException(InvalidArgumentException::class);
        
        $invalidInt = 'abc123';
        $this->sanitizationService->sanitizeInteger($invalidInt);
    }

    public function testSanitizeFloatReturnsValidFloat()
    {
        $validFloat = '123.45';
        $result = $this->sanitizationService->sanitizeFloat($validFloat);
        
        $this->assertEquals(123.45, $result);
        $this->assertIsFloat($result);
    }

    public function testSanitizeArraySanitizesAllElements()
    {
        $input = [
            'name' => '<script>alert("XSS")</script>John',
            'email' => 'john@example.com',
            'nested' => [
                'value' => '<iframe>malicious</iframe>test'
            ]
        ];

        $result = $this->sanitizationService->sanitizeArray($input);

        $this->assertStringNotContainsString('<script>', $result['name']);
        $this->assertStringContainsString('John', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertStringNotContainsString('<iframe>', $result['nested']['value']);
    }

    public function testSanitizeFilenameRemovesDangerousCharacters()
    {
        $maliciousFilename = '../../../etc/passwd.txt';
        $result = $this->sanitizationService->sanitizeFilename($maliciousFilename);
        
        $this->assertStringNotContainsString('../', $result);
        $this->assertStringNotContainsString('/', $result);
    }

    public function testSanitizeFilenamePreservesValidCharacters()
    {
        $validFilename = 'document-2024_01.pdf';
        $result = $this->sanitizationService->sanitizeFilename($validFilename);
        
        $this->assertEquals($validFilename, $result);
    }

    public function testSanitizeUrlValidatesCorrectFormat()
    {
        $validUrl = 'https://example.com/page?param=value';
        $result = $this->sanitizationService->sanitizeUrl($validUrl);
        
        $this->assertEquals($validUrl, $result);
    }

    public function testSanitizeUrlRejectsJavascriptScheme()
    {
        $this->expectException(InvalidArgumentException::class);
        
        $maliciousUrl = 'javascript:alert("XSS")';
        $this->sanitizationService->sanitizeUrl($maliciousUrl);
    }

    public function testSanitizeHtmlAllowsBasicTags()
    {
        $input = '<p>This is <strong>bold</strong> and <em>italic</em> text.</p>';
        $result = $this->sanitizationService->sanitizeHtml($input, ['p', 'strong', 'em']);
        
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<em>', $result);
    }

    public function testSanitizeHtmlRemovesDisallowedTags()
    {
        $input = '<p>Safe content</p><script>alert("XSS")</script>';
        $result = $this->sanitizationService->sanitizeHtml($input, ['p']);
        
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testSqlInjectionDetection()
    {
        $maliciousInput = "'; DROP TABLE users; --";
        $result = $this->sanitizationService->detectSqlInjection($maliciousInput);
        
        $this->assertTrue($result);
    }

    public function testSqlInjectionDetectionWithCleanInput()
    {
        $cleanInput = "Regular search term";
        $result = $this->sanitizationService->detectSqlInjection($cleanInput);
        
        $this->assertFalse($result);
    }

    public function testMaxLengthEnforcement()
    {
        $longString = str_repeat('a', 1000);
        $result = $this->sanitizationService->enforceMaxLength($longString, 100);
        
        $this->assertEquals(100, strlen($result));
        $this->assertTrue(str_ends_with($result, '...'));
    }

    public function testWhitelistValidation()
    {
        $allowedValues = ['pending', 'in_progress', 'completed'];
        
        $validValue = 'pending';
        $result = $this->sanitizationService->validateWhitelist($validValue, $allowedValues);
        $this->assertEquals($validValue, $result);
        
        $this->expectException(InvalidArgumentException::class);
        $invalidValue = 'invalid_status';
        $this->sanitizationService->validateWhitelist($invalidValue, $allowedValues);
    }
}