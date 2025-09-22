<?php

namespace Tests;

use Laravel\Lumen\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        
        // Ensure testing environment
        $app->make('config')->set('app.env', 'testing');
        
        return $app;
    }

    /**
     * Setup the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Only clear database if connection is available
        try {
            $this->clearTestDatabase();
        } catch (\Exception $e) {
            // Skip database cleanup if connection fails
            Log::info('Skipping database cleanup: ' . $e->getMessage());
        }
    }

    /**
     * Clean up the testing environment after each test.
     */
    protected function tearDown(): void
    {
        try {
            $this->clearTestDatabase();
        } catch (\Exception $e) {
            // Skip database cleanup if connection fails
            Log::info('Skipping database cleanup: ' . $e->getMessage());
        }
        
        parent::tearDown();
    }

    /**
     * Clear test database collections
     */
    protected function clearTestDatabase(): void
    {
        try {
            // Clear test collections
            if (class_exists('\App\Models\Task')) {
                \App\Models\Task::truncate();
            }
            if (class_exists('\App\Models\TaskLog')) {
                \App\Models\TaskLog::truncate();
            }
        } catch (\Exception $e) {
            // Ignore database errors during cleanup
            Log::info('Database cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a test task for testing purposes
     */
    protected function createTestTask(array $attributes = []): array
    {
        return array_merge([
            'title' => 'Test Task',
            'description' => 'This is a test task description',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => 'test@example.com',
            'due_date' => '2024-12-31'
        ], $attributes);
    }

    /**
     * Assert that the response has the expected API structure
     */
    protected function assertApiResponseStructure($response, array $structure = null): void
    {
        if ($structure === null) {
            $structure = [
                'success',
                'message',
                'data'
            ];
        }

        $this->seeJsonStructure($structure);
    }

    /**
     * Assert successful API response
     */
    protected function assertApiSuccess($response, $message = null): void
    {
        $this->assertApiResponseStructure($response);
        $this->seeJson(['success' => true]);
        
        if ($message) {
            $this->seeJson(['message' => $message]);
        }
    }

    /**
     * Assert API error response
     */
    protected function assertApiError($response, $message = null, $statusCode = 400): void
    {
        $this->assertResponseStatus($statusCode);
        $this->assertApiResponseStructure($response, [
            'success',
            'message',
            'errors'
        ]);
        $this->seeJson(['success' => false]);
        
        if ($message) {
            $this->seeJson(['message' => $message]);
        }
    }
}