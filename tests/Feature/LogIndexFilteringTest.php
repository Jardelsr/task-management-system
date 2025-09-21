<?php

namespace Tests\Feature;

use Laravel\Lumen\Testing\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use App\Models\Task;
use App\Models\TaskLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LogIndexFilteringTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tasks
        $this->testTask1 = Task::create([
            'title' => 'Test Task 1',
            'description' => 'Description for test task 1',
            'status' => 'pending'
        ]);

        $this->testTask2 = Task::create([
            'title' => 'Test Task 2', 
            'description' => 'Description for test task 2',
            'status' => 'completed'
        ]);

        // Create test logs with different attributes for filtering
        $this->createTestLogs();
    }

    private function createTestLogs()
    {
        $baseTime = Carbon::now()->subDays(5);

        // Create logs for task 1
        TaskLog::create([
            'task_id' => $this->testTask1->id,
            'action' => 'created',
            'user_id' => 1,
            'level' => 'info',
            'source' => 'api',
            'description' => 'Task created via API',
            'metadata' => json_encode(['ip' => '127.0.0.1']),
            'created_at' => $baseTime->copy()->addHours(1)
        ]);

        TaskLog::create([
            'task_id' => $this->testTask1->id,
            'action' => 'updated',
            'user_id' => 1,
            'level' => 'info',
            'source' => 'web',
            'description' => 'Task updated via web interface',
            'metadata' => json_encode(['field_changed' => 'status']),
            'created_at' => $baseTime->copy()->addHours(2)
        ]);

        // Create logs for task 2
        TaskLog::create([
            'task_id' => $this->testTask2->id,
            'action' => 'created',
            'user_id' => 2,
            'level' => 'info',
            'source' => 'api',
            'description' => 'Task created via API',
            'metadata' => json_encode(['batch_id' => 'batch_123']),
            'created_at' => $baseTime->copy()->addHours(3)
        ]);

        TaskLog::create([
            'task_id' => $this->testTask2->id,
            'action' => 'deleted',
            'user_id' => 2,
            'level' => 'warning',
            'source' => 'web',
            'description' => 'Task soft deleted',
            'metadata' => json_encode(['deletion_type' => 'soft']),
            'created_at' => $baseTime->copy()->addHours(4)
        ]);

        // Create an error log
        TaskLog::create([
            'task_id' => null,
            'action' => 'system_error',
            'user_id' => null,
            'level' => 'error',
            'source' => 'system',
            'description' => 'System error occurred',
            'metadata' => json_encode(['error_code' => 'E500']),
            'created_at' => $baseTime->copy()->addHours(5)
        ]);
    }

    /**
     * Test basic log index without filters
     */
    public function testLogIndexWithoutFilters()
    {
        $response = $this->get('/api/v1/logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data',
            'meta' => [
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to',
                    'has_next_page',
                    'has_previous_page'
                ]
            ]
        ]);

        $responseData = json_decode($response->getContent(), true);
        $this->assertGreaterThanOrEqual(5, count($responseData['data']));
    }

    /**
     * Test filtering by task ID
     */
    public function testLogIndexFilterByTaskId()
    {
        $response = $this->get('/api/v1/logs?task_id=' . $this->testTask1->id);

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertEquals(2, count($responseData['data']));
        
        // All logs should be for the specified task
        foreach ($responseData['data'] as $log) {
            $this->assertEquals($this->testTask1->id, $log['task_id']);
        }

        // Check applied filters in response
        $this->assertArrayHasKey('filters', $responseData['meta']);
        $this->assertEquals($this->testTask1->id, $responseData['meta']['filters']['task_id']);
    }

    /**
     * Test filtering by action
     */
    public function testLogIndexFilterByAction()
    {
        $response = $this->get('/api/v1/logs?action=created');

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertEquals(2, count($responseData['data']));
        
        // All logs should have the specified action
        foreach ($responseData['data'] as $log) {
            $this->assertEquals('created', $log['action']);
        }
    }

    /**
     * Test filtering by user ID
     */
    public function testLogIndexFilterByUserId()
    {
        $response = $this->get('/api/v1/logs?user_id=2');

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertEquals(2, count($responseData['data']));
        
        // All logs should be for the specified user
        foreach ($responseData['data'] as $log) {
            $this->assertEquals(2, $log['user_id']);
        }
    }

    /**
     * Test filtering by level
     */
    public function testLogIndexFilterByLevel()
    {
        $response = $this->get('/api/v1/logs?level=error');

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertEquals(1, count($responseData['data']));
        $this->assertEquals('error', $responseData['data'][0]['level']);
    }

    /**
     * Test filtering by source
     */
    public function testLogIndexFilterBySource()
    {
        $response = $this->get('/api/v1/logs?source=api');

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertEquals(2, count($responseData['data']));
        
        // All logs should be from the specified source
        foreach ($responseData['data'] as $log) {
            $this->assertEquals('api', $log['source']);
        }
    }

    /**
     * Test date range filtering
     */
    public function testLogIndexFilterByDateRange()
    {
        $startDate = Carbon::now()->subDays(6)->format('Y-m-d H:i:s');
        $endDate = Carbon::now()->subDays(3)->format('Y-m-d H:i:s');

        $response = $this->get("/api/v1/logs?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        // Should have logs within the date range
        $this->assertGreaterThan(0, count($responseData['data']));

        // Check that statistics include date range info
        if (isset($responseData['meta']['statistics']['date_range'])) {
            $this->assertArrayHasKey('start', $responseData['meta']['statistics']['date_range']);
            $this->assertArrayHasKey('end', $responseData['meta']['statistics']['date_range']);
        }
    }

    /**
     * Test combined filters
     */
    public function testLogIndexWithCombinedFilters()
    {
        $response = $this->get('/api/v1/logs?task_id=' . $this->testTask1->id . '&action=updated&level=info');

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertEquals(1, count($responseData['data']));
        
        $log = $responseData['data'][0];
        $this->assertEquals($this->testTask1->id, $log['task_id']);
        $this->assertEquals('updated', $log['action']);
        $this->assertEquals('info', $log['level']);

        // Check applied filters count
        $this->assertEquals(3, $responseData['meta']['statistics']['applied_filters_count']);
    }

    /**
     * Test sorting functionality
     */
    public function testLogIndexWithSorting()
    {
        // Test ascending sort by action
        $response = $this->get('/api/v1/logs?sort_by=action&sort_order=asc');

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertGreaterThan(0, count($responseData['data']));

        // Verify headers contain sort information
        $response->assertHeader('X-Sort-By', 'action');
        $response->assertHeader('X-Sort-Order', 'asc');

        // Verify sorting is applied (first action should be alphabetically first)
        $actions = array_column($responseData['data'], 'action');
        $sortedActions = $actions;
        sort($sortedActions);
        $this->assertEquals($sortedActions, $actions);
    }

    /**
     * Test pagination with filters
     */
    public function testLogIndexPaginationWithFilters()
    {
        // Test with limit
        $response = $this->get('/api/v1/logs?limit=2&page=1');

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertLessThanOrEqual(2, count($responseData['data']));
        
        // Check pagination metadata
        $pagination = $responseData['meta']['pagination'];
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(2, $pagination['per_page']);
        $this->assertIsInt($pagination['total']);
        
        // Check headers
        $response->assertHeader('X-Total-Count', $pagination['total']);
        $response->assertHeader('X-Page', '1');
        $response->assertHeader('X-Per-Page', '2');
    }

    /**
     * Test invalid filter parameters
     */
    public function testLogIndexWithInvalidFilters()
    {
        // Invalid task_id (non-integer)
        $response = $this->get('/api/v1/logs?task_id=invalid');
        $response->assertStatus(422);

        // Invalid date format
        $response = $this->get('/api/v1/logs?start_date=invalid-date');
        $response->assertStatus(422);

        // Invalid level
        $response = $this->get('/api/v1/logs?level=invalid_level');
        $response->assertStatus(422);

        // Invalid sort order
        $response = $this->get('/api/v1/logs?sort_order=invalid');
        $response->assertStatus(422);
    }

    /**
     * Test empty result handling
     */
    public function testLogIndexEmptyResults()
    {
        $response = $this->get('/api/v1/logs?task_id=99999'); // Non-existent task

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertEquals(0, count($responseData['data']));
        $this->assertStringContainsString('No logs found', $responseData['message']);
    }

    /**
     * Test statistics in response
     */
    public function testLogIndexStatistics()
    {
        $response = $this->get('/api/v1/logs?action=created');

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent(), true);
        
        $statistics = $responseData['meta']['statistics'];
        $this->assertArrayHasKey('total_logs', $statistics);
        $this->assertArrayHasKey('logs_returned', $statistics);
        $this->assertArrayHasKey('applied_filters_count', $statistics);
        $this->assertArrayHasKey('has_filters', $statistics);
        
        $this->assertTrue($statistics['has_filters']);
        $this->assertEquals(1, $statistics['applied_filters_count']);
        $this->assertEquals('created', $statistics['filtered_by_action']);
    }

    /**
     * Test response headers
     */
    public function testLogIndexResponseHeaders()
    {
        $response = $this->get('/api/v1/logs?task_id=' . $this->testTask1->id);

        $response->assertStatus(200);
        
        // Check required headers
        $response->assertHeader('X-Total-Count');
        $response->assertHeader('X-Page');
        $response->assertHeader('X-Per-Page');
        $response->assertHeader('X-Total-Pages');
        $response->assertHeader('X-Applied-Filters');
        $response->assertHeader('X-API-Version');
        $response->assertHeader('X-Query-Execution-Time');
    }

    /**
     * Test maximum limit enforcement
     */
    public function testLogIndexMaximumLimit()
    {
        $response = $this->get('/api/v1/logs?limit=2000'); // Exceeds maximum of 1000

        $response->assertStatus(422);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertStringContainsString('limit', strtolower(json_encode($responseData['errors'])));
    }
}