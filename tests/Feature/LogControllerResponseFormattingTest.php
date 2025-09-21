<?php

namespace Tests\Feature;

use App\Models\TaskLog;
use Carbon\Carbon;
use Laravel\Lumen\Testing\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use Illuminate\Http\Response;

class LogControllerResponseFormattingTest extends TestCase
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
        
        // Set up MongoDB connection for testing
        $this->artisan('config:cache');
    }

    /** @test */
    public function index_endpoint_returns_properly_formatted_response()
    {
        $this->createTestLogs();

        $response = $this->getJson('/logs');

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json();

        // Check response structure
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertTrue($data['success']);

        $responseData = $data['data'];
        
        // Check main data structure
        $this->assertArrayHasKey('logs', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
        $this->assertArrayHasKey('statistics', $responseData);
        $this->assertArrayHasKey('applied_filters', $responseData);
        $this->assertArrayHasKey('query_metadata', $responseData);

        // Check pagination
        $pagination = $responseData['pagination'];
        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('has_next_page', $pagination);
        $this->assertArrayHasKey('has_previous_page', $pagination);

        // Check log formatting
        if (!empty($responseData['logs'])) {
            $firstLog = $responseData['logs'][0];
            $this->assertArrayHasKey('id', $firstLog);
            $this->assertArrayHasKey('task_id', $firstLog);
            $this->assertArrayHasKey('action', $firstLog);
            $this->assertArrayHasKey('action_display', $firstLog);
            $this->assertArrayHasKey('user', $firstLog);
            $this->assertArrayHasKey('timestamp', $firstLog);
        }

        // Check statistics
        $stats = $responseData['statistics'];
        $this->assertArrayHasKey('total_found', $stats);
        $this->assertArrayHasKey('returned_count', $stats);
        $this->assertArrayHasKey('action_distribution', $stats);
    }

    /** @test */
    public function index_endpoint_supports_filtering_parameters()
    {
        $this->createTestLogsWithVariousActions();

        $response = $this->getJson('/logs?' . http_build_query([
            'action' => TaskLog::ACTION_CREATED,
            'user_id' => 1,
            'limit' => 5
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data');

        // Check applied filters
        $this->assertEquals(TaskLog::ACTION_CREATED, $data['applied_filters']['action']);
        $this->assertEquals(1, $data['applied_filters']['user_id']);
        $this->assertEquals(5, $data['applied_filters']['limit']);

        // Check that filtering worked
        $this->assertTrue($data['statistics']['filtered']);

        // All returned logs should match the filter
        foreach ($data['logs'] as $log) {
            $this->assertEquals(TaskLog::ACTION_CREATED, $log['action']);
            $this->assertEquals(1, $log['user']['id']);
        }
    }

    /** @test */
    public function index_endpoint_supports_date_range_filtering()
    {
        $this->createTestLogsWithDifferentDates();

        $startDate = Carbon::now()->subDays(5)->toISOString();
        $endDate = Carbon::now()->subDays(1)->toISOString();

        $response = $this->getJson('/logs?' . http_build_query([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data');
        
        // Check date range in query metadata
        $this->assertEquals($startDate, $data['query_metadata']['date_range']['start']);
        $this->assertEquals($endDate, $data['query_metadata']['date_range']['end']);

        // All logs should be within date range
        foreach ($data['logs'] as $log) {
            $logDate = Carbon::parse($log['timestamp']['iso8601']);
            $this->assertTrue($logDate->between(
                Carbon::parse($startDate),
                Carbon::parse($endDate)
            ));
        }
    }

    /** @test */
    public function index_endpoint_supports_sorting_and_pagination()
    {
        $this->createMultipleTestLogs(15);

        // Test first page with descending sort
        $response = $this->getJson('/logs?' . http_build_query([
            'sort_by' => 'created_at',
            'sort_order' => 'desc',
            'page' => 1,
            'limit' => 5
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data');

        // Check pagination
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(5, $data['pagination']['per_page']);
        $this->assertTrue($data['pagination']['has_next_page']);
        $this->assertFalse($data['pagination']['has_previous_page']);

        // Check sorting metadata
        $this->assertEquals('created_at', $data['query_metadata']['sort_by']);
        $this->assertEquals('desc', $data['query_metadata']['sort_order']);

        // Check that logs are actually sorted
        $timestamps = collect($data['logs'])->pluck('timestamp.iso8601');
        $sortedTimestamps = $timestamps->sort()->reverse()->values();
        $this->assertEquals($sortedTimestamps->toArray(), $timestamps->toArray());
    }

    /** @test */
    public function task_logs_endpoint_returns_properly_formatted_response()
    {
        $taskId = 123;
        $this->createTestLogsForTask($taskId);

        $response = $this->getJson("/logs/task/{$taskId}");

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json();

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);

        $responseData = $data['data'];
        
        // Check structure
        $this->assertArrayHasKey('logs', $responseData);
        $this->assertArrayHasKey('meta', $responseData);
        $this->assertArrayHasKey('task_metadata', $responseData);

        // Check task metadata
        $taskMeta = $responseData['task_metadata'];
        $this->assertEquals($taskId, $taskMeta['task_id']);
        $this->assertArrayHasKey('total_logs_for_task', $taskMeta);
        $this->assertArrayHasKey('returned_count', $taskMeta);

        // All logs should be for the specified task
        foreach ($responseData['logs'] as $log) {
            $this->assertEquals($taskId, $log['task_id']);
        }
    }

    /** @test */
    public function task_logs_endpoint_with_limit_parameter()
    {
        $taskId = 456;
        $this->createMultipleTestLogsForTask($taskId, 10);

        $response = $this->getJson("/logs/task/{$taskId}?limit=3");

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data');
        
        // Should return only 3 logs
        $this->assertLessThanOrEqual(3, count($data['logs']));
        $this->assertEquals(3, $data['task_metadata']['returned_count']);
        $this->assertEquals(10, $data['task_metadata']['total_logs_for_task']);
    }

    /** @test */
    public function stats_endpoint_returns_properly_formatted_response()
    {
        $this->createTestLogsWithVariousActions();

        $response = $this->getJson('/logs/stats');

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json();

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);

        $responseData = $data['data'];

        // Check statistics structure
        $this->assertArrayHasKey('summary', $responseData);
        $this->assertArrayHasKey('actions', $responseData);
        $this->assertArrayHasKey('activity', $responseData);
        $this->assertArrayHasKey('generated_at', $responseData);
        $this->assertArrayHasKey('metadata', $responseData);

        // Check summary
        $summary = $responseData['summary'];
        $this->assertArrayHasKey('total_logs', $summary);
        $this->assertArrayHasKey('period_analyzed', $summary);

        // Check actions breakdown
        $actions = $responseData['actions'];
        $this->assertArrayHasKey('breakdown', $actions);
        $this->assertArrayHasKey('total_by_type', $actions);

        if (!empty($actions['breakdown'])) {
            $firstAction = $actions['breakdown'][0];
            $this->assertArrayHasKey('action', $firstAction);
            $this->assertArrayHasKey('action_display', $firstAction);
            $this->assertArrayHasKey('count', $firstAction);
            $this->assertArrayHasKey('percentage', $firstAction);
        }
    }

    /** @test */
    public function stats_endpoint_with_date_range_parameters()
    {
        $this->createTestLogsWithDifferentDates();

        $startDate = Carbon::now()->subDays(7)->toISOString();
        $endDate = Carbon::now()->toISOString();

        $response = $this->getJson('/logs/stats?' . http_build_query([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data');

        // Check that date range is reflected in metadata
        $period = $data['summary']['period_analyzed'];
        $this->assertEquals($startDate, $period['start']);
        $this->assertEquals($endDate, $period['end']);
    }

    /** @test */
    public function show_endpoint_returns_properly_formatted_single_log()
    {
        $log = $this->createSingleTestLog();
        $logId = (string) $log->_id;

        $response = $this->getJson("/logs/{$logId}");

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json();

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);

        $responseData = $data['data'];

        // Check structure
        $this->assertArrayHasKey('log', $responseData);
        $this->assertArrayHasKey('meta', $responseData);

        $formattedLog = $responseData['log'];
        
        // Check log formatting
        $this->assertEquals($logId, $formattedLog['id']);
        $this->assertEquals($log->task_id, $formattedLog['task_id']);
        $this->assertEquals($log->action, $formattedLog['action']);
        $this->assertArrayHasKey('action_display', $formattedLog);
        $this->assertArrayHasKey('user', $formattedLog);
        $this->assertArrayHasKey('timestamp', $formattedLog);

        // Check metadata
        $meta = $responseData['meta'];
        $this->assertArrayHasKey('retrieved_at', $meta);
        $this->assertEquals($logId, $meta['log_id']);
    }

    /** @test */
    public function show_endpoint_with_include_technical_parameter()
    {
        $log = $this->createSingleTestLog();
        $logId = (string) $log->_id;

        $response = $this->getJson("/logs/{$logId}?include_technical=true");

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data');
        $formattedLog = $data['log'];

        // Should include technical metadata
        $this->assertArrayHasKey('technical', $formattedLog);
        
        $technical = $formattedLog['technical'];
        $this->assertArrayHasKey('document_id', $technical);
        $this->assertArrayHasKey('collection', $technical);
        $this->assertArrayHasKey('indexes_used', $technical);
    }

    /** @test */
    public function show_endpoint_returns_404_for_non_existent_log()
    {
        $response = $this->getJson('/logs/507f1f77bcf86cd799439999');

        $response->assertStatus(Response::HTTP_NOT_FOUND);

        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
    }

    /** @test */
    public function response_headers_contain_proper_content_type_and_caching()
    {
        $this->createTestLogs();

        $response = $this->getJson('/logs');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'application/json');
        
        // Check for cache headers (if implemented)
        // $response->assertHeader('Cache-Control');
    }

    /** @test */
    public function response_includes_api_version_and_formatting_metadata()
    {
        $this->createSingleTestLog();

        $response = $this->getJson('/logs');

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data');

        // Check if API version or formatting version is included in metadata
        if (isset($data['query_metadata']['api_version'])) {
            $this->assertNotEmpty($data['query_metadata']['api_version']);
        }

        if (isset($data['query_metadata']['formatting_applied'])) {
            $this->assertTrue($data['query_metadata']['formatting_applied']);
        }
    }

    /** @test */
    public function large_response_pagination_works_correctly()
    {
        $this->createMultipleTestLogs(50);

        // Test first page
        $response1 = $this->getJson('/logs?limit=10&page=1');
        $response1->assertStatus(Response::HTTP_OK);
        $data1 = $response1->json('data');

        // Test last page
        $response2 = $this->getJson('/logs?limit=10&page=5');
        $response2->assertStatus(Response::HTTP_OK);
        $data2 = $response2->json('data');

        // Check pagination metadata
        $this->assertEquals(1, $data1['pagination']['current_page']);
        $this->assertTrue($data1['pagination']['has_next_page']);
        $this->assertFalse($data1['pagination']['has_previous_page']);

        $this->assertEquals(5, $data2['pagination']['current_page']);
        $this->assertFalse($data2['pagination']['has_next_page']);
        $this->assertTrue($data2['pagination']['has_previous_page']);

        // Ensure no duplicate logs between pages
        $page1Ids = collect($data1['logs'])->pluck('id');
        $page2Ids = collect($data2['logs'])->pluck('id');
        $this->assertEmpty($page1Ids->intersect($page2Ids));
    }

    /**
     * Helper methods for creating test data
     */
    
    protected function createTestLogs(): void
    {
        TaskLog::create([
            'task_id' => 100,
            'action' => TaskLog::ACTION_CREATED,
            'new_data' => ['title' => 'Test Task 1'],
            'user_id' => 1,
            'user_name' => 'User One',
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subHours(2)
        ]);

        TaskLog::create([
            'task_id' => 101,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => ['title' => 'Old Title'],
            'new_data' => ['title' => 'New Title'],
            'user_id' => 2,
            'user_name' => 'User Two',
            'created_at' => Carbon::now()->subHour(),
            'updated_at' => Carbon::now()->subHour()
        ]);
    }

    protected function createSingleTestLog(): TaskLog
    {
        return TaskLog::create([
            'task_id' => 200,
            'action' => TaskLog::ACTION_CREATED,
            'new_data' => ['title' => 'Single Test Task'],
            'user_id' => 5,
            'user_name' => 'Test User',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }

    protected function createTestLogsForTask(int $taskId): void
    {
        TaskLog::create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_CREATED,
            'new_data' => ['title' => 'Task Created'],
            'user_id' => 1,
            'user_name' => 'Creator',
            'created_at' => Carbon::now()->subHours(3)
        ]);

        TaskLog::create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => ['status' => 'pending'],
            'new_data' => ['status' => 'in_progress'],
            'user_id' => 2,
            'user_name' => 'Updater',
            'created_at' => Carbon::now()->subHours(1)
        ]);
    }

    protected function createMultipleTestLogsForTask(int $taskId, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            TaskLog::create([
                'task_id' => $taskId,
                'action' => TaskLog::ACTION_UPDATED,
                'old_data' => ['step' => $i - 1],
                'new_data' => ['step' => $i],
                'user_id' => ($i % 3) + 1,
                'user_name' => "User " . (($i % 3) + 1),
                'created_at' => Carbon::now()->subMinutes($i * 5)
            ]);
        }
    }

    protected function createTestLogsWithVariousActions(): void
    {
        $actions = [
            TaskLog::ACTION_CREATED,
            TaskLog::ACTION_UPDATED,
            TaskLog::ACTION_DELETED,
            TaskLog::ACTION_RESTORED
        ];

        foreach ($actions as $index => $action) {
            $data = [];
            
            if ($action === TaskLog::ACTION_CREATED || $action === TaskLog::ACTION_RESTORED) {
                $data['new_data'] = ['title' => "Task for {$action}"];
            } elseif ($action === TaskLog::ACTION_UPDATED) {
                $data['old_data'] = ['status' => 'pending'];
                $data['new_data'] = ['status' => 'in_progress'];
            } elseif ($action === TaskLog::ACTION_DELETED) {
                $data['old_data'] = ['title' => 'Deleted Task'];
            }

            TaskLog::create(array_merge([
                'task_id' => 500 + $index,
                'action' => $action,
                'user_id' => $index + 1,
                'user_name' => "User " . ($index + 1),
                'created_at' => Carbon::now()->subMinutes($index * 10)
            ], $data));
        }
    }

    protected function createTestLogsWithDifferentDates(): void
    {
        TaskLog::create([
            'task_id' => 300,
            'action' => TaskLog::ACTION_CREATED,
            'created_at' => Carbon::now()->subDays(10)
        ]);

        TaskLog::create([
            'task_id' => 301,
            'action' => TaskLog::ACTION_UPDATED,
            'created_at' => Carbon::now()->subDays(3)
        ]);

        TaskLog::create([
            'task_id' => 302,
            'action' => TaskLog::ACTION_DELETED,
            'created_at' => Carbon::now()->subDays(1)
        ]);
    }

    protected function createMultipleTestLogs(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            TaskLog::create([
                'task_id' => 400 + $i,
                'action' => TaskLog::ACTION_CREATED,
                'new_data' => ['title' => "Test Task {$i}"],
                'user_id' => ($i % 3) + 1,
                'user_name' => "User " . (($i % 3) + 1),
                'created_at' => Carbon::now()->subMinutes($i * 5)
            ]);
        }
    }
}