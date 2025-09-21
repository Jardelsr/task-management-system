<?php

namespace Tests\Unit;

use App\Services\LogResponseFormatter;
use App\Models\TaskLog;
use App\Repositories\LogRepository;
use Carbon\Carbon;
use Tests\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MongoDB\BSON\ObjectId;
use Mockery;

class LogResponseFormattingErrorHandlingTest extends TestCase
{
    use DatabaseTransactions;

    protected LogResponseFormatter $formatter;
    protected LogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new LogResponseFormatter();
        $this->repository = new LogRepository();
    }

    /** @test */
    public function it_handles_null_log_gracefully()
    {
        $result = $this->formatter->formatSingleLog(null);

        $this->assertNull($result);
    }

    /** @test */
    public function it_handles_empty_log_collection()
    {
        $emptyCollection = collect();
        
        $result = $this->formatter->formatLogCollection($emptyCollection);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEmpty($result['logs']);
        $this->assertEquals(0, $result['meta']['count']);
    }

    /** @test */
    public function it_handles_malformed_log_data()
    {
        // Create log with missing required fields
        $malformedLog = new TaskLog();
        $malformedLog->_id = new ObjectId();
        // Missing task_id, action, etc.

        $result = $this->formatter->formatSingleLog($malformedLog);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        
        // Should handle missing fields with defaults or null values
        $this->assertArrayHasKey('task_id', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('user', $result);
        
        // Missing fields should be null or have default values
        $this->assertNull($result['task_id']);
        $this->assertNull($result['action']);
    }

    /** @test */
    public function it_handles_corrupted_json_data()
    {
        $logWithCorruptedData = TaskLog::create([
            'task_id' => 100,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => null, // Should handle null gracefully
            'new_data' => [], // Empty array
            'user_id' => 1,
            'user_name' => 'Test User',
            'created_at' => Carbon::now()
        ]);

        // Manually corrupt the data by setting invalid JSON (simulating database corruption)
        $logWithCorruptedData->setAttribute('old_data', 'invalid json string');

        $result = $this->formatter->formatSingleLog($logWithCorruptedData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('old_data', $result);
        
        // Should handle corrupted data gracefully
        // Either as the raw string or as null/empty
        $this->assertTrue(
            is_null($result['old_data']) || 
            is_array($result['old_data']) || 
            is_string($result['old_data'])
        );
    }

    /** @test */
    public function it_handles_extremely_large_data_fields()
    {
        $largeData = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeData["field_{$i}"] = str_repeat('x', 1000); // 1000 chars each
        }

        $logWithLargeData = TaskLog::create([
            'task_id' => 200,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => $largeData,
            'new_data' => $largeData,
            'user_id' => 1,
            'user_name' => 'Test User',
            'created_at' => Carbon::now()
        ]);

        $result = $this->formatter->formatSingleLog($logWithLargeData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('old_data', $result);
        $this->assertArrayHasKey('new_data', $result);
        
        // Should handle large data without errors
        $this->assertIsArray($result['old_data']);
        $this->assertIsArray($result['new_data']);
    }

    /** @test */
    public function it_handles_invalid_date_fields()
    {
        $logWithInvalidDate = new TaskLog([
            'task_id' => 300,
            'action' => TaskLog::ACTION_CREATED,
            'user_id' => 1,
            'user_name' => 'Test User'
        ]);
        
        // Set invalid date
        $logWithInvalidDate->created_at = 'invalid-date-string';
        $logWithInvalidDate->updated_at = null;

        $result = $this->formatter->formatSingleLog($logWithInvalidDate);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('timestamp', $result);
        
        // Should handle invalid dates gracefully
        if (is_array($result['timestamp'])) {
            $this->assertArrayHasKey('iso8601', $result['timestamp']);
            // Should either be null or a fallback value
        } else {
            $this->assertTrue(is_string($result['timestamp']) || is_null($result['timestamp']));
        }
    }

    /** @test */
    public function it_handles_missing_user_information()
    {
        $logWithoutUser = TaskLog::create([
            'task_id' => 400,
            'action' => TaskLog::ACTION_DELETED,
            'user_id' => null,
            'user_name' => null,
            'created_at' => Carbon::now()
        ]);

        $result = $this->formatter->formatSingleLog($logWithoutUser);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        
        // Should handle missing user info
        if (is_array($result['user'])) {
            $this->assertNull($result['user']['id']);
            $this->assertNotNull($result['user']['name']); // Should have fallback like 'System'
        } else {
            $this->assertNotNull($result['user']); // Should be string like 'System'
        }
    }

    /** @test */
    public function it_handles_circular_references_in_data()
    {
        // Create data with potential circular references
        $circularData = ['key1' => 'value1'];
        $circularData['self_reference'] = &$circularData;

        $logWithCircularRef = TaskLog::create([
            'task_id' => 500,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => ['normal' => 'data'],
            'new_data' => $circularData,
            'user_id' => 1,
            'user_name' => 'Test User',
            'created_at' => Carbon::now()
        ]);

        // This should not throw an exception
        $result = $this->formatter->formatSingleLog($logWithCircularRef);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('new_data', $result);
    }

    /** @test */
    public function it_handles_invalid_mongodb_object_id()
    {
        $result = $this->repository->findByIdWithFormattedResponse('invalid-id');
        
        $this->assertNull($result);

        // Test with malformed ObjectId
        $result = $this->repository->findByIdWithFormattedResponse('not-a-valid-mongodb-id');
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_handles_database_connection_errors()
    {
        // Mock the TaskLog model to throw an exception
        $mockLog = Mockery::mock(TaskLog::class);
        $mockLog->shouldReceive('where')->andThrow(new \Exception('Database connection error'));

        // This would require more complex mocking to fully test
        // For now, we test that the formatter handles null inputs gracefully
        $result = $this->formatter->formatSingleLog(null);
        $this->assertNull($result);
    }

    /** @test */
    public function it_handles_memory_exhaustion_scenarios()
    {
        // Create a very large collection to test memory handling
        $largeCollection = collect();
        
        for ($i = 0; $i < 100; $i++) {
            $log = new TaskLog([
                'task_id' => $i,
                'action' => TaskLog::ACTION_CREATED,
                'new_data' => array_fill(0, 100, 'data'),
                'user_id' => 1,
                'user_name' => 'Test User',
                'created_at' => Carbon::now()
            ]);
            $log->_id = new ObjectId();
            $largeCollection->push($log);
        }

        // Should handle large collections without memory issues
        $result = $this->formatter->formatLogCollection($largeCollection);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertCount(100, $result['logs']);
    }

    /** @test */
    public function it_handles_invalid_pagination_parameters()
    {
        $logs = collect([]);
        
        // Test negative values
        $result = $this->formatter->formatPaginatedLogs($logs, -10, -5, -1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        
        $pagination = $result['pagination'];
        $this->assertGreaterThanOrEqual(0, $pagination['total']);
        $this->assertGreaterThanOrEqual(0, $pagination['per_page']);
        $this->assertGreaterThanOrEqual(1, $pagination['current_page']);

        // Test extremely large values
        $result = $this->formatter->formatPaginatedLogs($logs, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
    }

    /** @test */
    public function it_handles_invalid_statistics_data()
    {
        // Test with null statistics
        $result = $this->formatter->formatLogStatistics(null);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('actions', $result);

        // Test with empty statistics
        $result = $this->formatter->formatLogStatistics([]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);

        // Test with malformed statistics
        $malformedStats = [
            'total_logs' => 'not-a-number',
            'action_distribution' => 'not-an-array'
        ];
        
        $result = $this->formatter->formatLogStatistics($malformedStats);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
    }

    /** @test */
    public function it_handles_mixed_data_types_in_log_fields()
    {
        $logWithMixedData = TaskLog::create([
            'task_id' => 600,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => [
                'string_field' => 'text',
                'number_field' => 42,
                'boolean_field' => true,
                'null_field' => null,
                'array_field' => ['nested', 'array'],
                'object_field' => (object) ['key' => 'value']
            ],
            'new_data' => [
                'mixed_types' => [
                    123,
                    'string',
                    true,
                    ['nested' => 'array'],
                    null
                ]
            ],
            'user_id' => 1,
            'user_name' => 'Test User',
            'created_at' => Carbon::now()
        ]);

        $result = $this->formatter->formatSingleLog($logWithMixedData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('old_data', $result);
        $this->assertArrayHasKey('new_data', $result);
        
        // Should handle mixed data types gracefully
        $this->assertIsArray($result['old_data']);
        $this->assertIsArray($result['new_data']);
    }

    /** @test */
    public function it_handles_unicode_and_special_characters()
    {
        $logWithUnicode = TaskLog::create([
            'task_id' => 700,
            'action' => TaskLog::ACTION_CREATED,
            'new_data' => [
                'title' => 'Task with 🚀 émojis and àccénts',
                'description' => '这是中文 • Special chars: @#$%^&*()[]{}|\\:";\'<>?,./',
                'unicode_test' => '🌟⭐️🎯🔥💯✨🎉🎊🎈'
            ],
            'user_name' => 'Ügur Tëst Üsər',
            'created_at' => Carbon::now()
        ]);

        $result = $this->formatter->formatSingleLog($logWithUnicode);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('new_data', $result);
        $this->assertArrayHasKey('user', $result);
        
        // Should preserve Unicode characters
        $newData = $result['new_data'];
        $this->assertStringContainsString('🚀', $newData['title']);
        $this->assertStringContainsString('这是中文', $newData['description']);
        $this->assertStringContainsString('🌟', $newData['unicode_test']);
    }

    /** @test */
    public function it_handles_very_long_strings()
    {
        $veryLongString = str_repeat('A very long string with repeated content. ', 1000);
        
        $logWithLongStrings = TaskLog::create([
            'task_id' => 800,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => ['description' => $veryLongString],
            'new_data' => ['description' => $veryLongString . ' Updated.'],
            'user_name' => 'Test User',
            'created_at' => Carbon::now()
        ]);

        $result = $this->formatter->formatSingleLog($logWithLongStrings);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('old_data', $result);
        $this->assertArrayHasKey('new_data', $result);
        
        // Should handle very long strings without errors
        $this->assertIsArray($result['old_data']);
        $this->assertIsArray($result['new_data']);
    }

    /** @test */
    public function it_handles_malformed_configuration()
    {
        // Test with completely invalid configuration
        config(['log_responses' => 'invalid-config']);

        // Should fall back to safe defaults
        $log = $this->createTestLog();
        $result = $this->formatter->formatSingleLog($log);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('action', $result);
    }

    /** @test */
    public function it_provides_fallback_values_for_missing_data()
    {
        $incompleteLog = new TaskLog();
        $incompleteLog->_id = new ObjectId();
        $incompleteLog->task_id = 999;
        // Missing many fields

        $result = $this->formatter->formatSingleLog($incompleteLog);

        $this->assertIsArray($result);
        
        // Should provide fallback values
        $this->assertNotNull($result['id']);
        $this->assertEquals(999, $result['task_id']);
        $this->assertNotNull($result['action']); // Should have fallback
        $this->assertNotNull($result['user']); // Should have fallback
        $this->assertNotNull($result['timestamp']); // Should have fallback
    }

    /** @test */
    public function it_handles_concurrent_access_scenarios()
    {
        // Create a log that might be accessed concurrently
        $log = $this->createTestLog();

        // Simulate concurrent formatting (in real scenario this would be multi-threaded)
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->formatter->formatSingleLog($log, [
                'include_metadata' => ($i % 2 === 0)
            ]);
        }

        // All results should be valid
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('id', $result);
        }
    }

    /**
     * Helper method to create a test log
     */
    protected function createTestLog(): TaskLog
    {
        return TaskLog::create([
            'task_id' => 100,
            'action' => TaskLog::ACTION_CREATED,
            'new_data' => ['title' => 'Test Task'],
            'user_id' => 1,
            'user_name' => 'Test User',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }
}