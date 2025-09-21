<?php

namespace Tests\Unit;

use App\Services\LogResponseFormatter;
use App\Models\TaskLog;
use App\Repositories\LogRepository;
use Carbon\Carbon;
use Tests\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MongoDB\BSON\ObjectId;

class LogResponseFormattingPerformanceTest extends TestCase
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
    public function it_formats_single_log_within_performance_threshold()
    {
        $log = $this->createTestLog();

        $startTime = microtime(true);
        $result = $this->formatter->formatSingleLog($log);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->assertIsArray($result);
        $this->assertLessThan(10, $executionTime, 'Single log formatting should take less than 10ms');
    }

    /** @test */
    public function it_formats_small_log_collection_efficiently()
    {
        $logs = $this->createLogCollection(10);

        $startTime = microtime(true);
        $result = $this->formatter->formatLogCollection($logs);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertCount(10, $result['logs']);
        $this->assertLessThan(50, $executionTime, 'Small collection formatting should take less than 50ms');
    }

    /** @test */
    public function it_formats_medium_log_collection_within_reasonable_time()
    {
        $logs = $this->createLogCollection(100);

        $startTime = microtime(true);
        $result = $this->formatter->formatLogCollection($logs);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertCount(100, $result['logs']);
        $this->assertLessThan(200, $executionTime, 'Medium collection formatting should take less than 200ms');
    }

    /** @test */
    public function it_formats_large_log_collection_efficiently()
    {
        $logs = $this->createLogCollection(1000);

        $startTime = microtime(true);
        $result = $this->formatter->formatLogCollection($logs);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertCount(1000, $result['logs']);
        $this->assertLessThan(2000, $executionTime, 'Large collection formatting should take less than 2 seconds');
    }

    /** @test */
    public function it_handles_pagination_formatting_performance()
    {
        $logs = $this->createLogCollection(500);

        $startTime = microtime(true);
        $result = $this->formatter->formatPaginatedLogs($logs, 5000, 50, 0, [
            'include_metadata' => true
        ]);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertLessThan(1000, $executionTime, 'Paginated formatting should take less than 1 second');
    }

    /** @test */
    public function it_formats_statistics_efficiently()
    {
        $statisticsData = $this->generateLargeStatisticsData();

        $startTime = microtime(true);
        $result = $this->formatter->formatLogStatistics($statisticsData, [
            'detailed_breakdown' => true
        ]);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertLessThan(100, $executionTime, 'Statistics formatting should take less than 100ms');
    }

    /** @test */
    public function it_measures_memory_usage_for_large_collections()
    {
        $memoryBefore = memory_get_usage(true);
        $logs = $this->createLogCollection(1000);
        $memoryAfterCreation = memory_get_usage(true);

        $result = $this->formatter->formatLogCollection($logs);
        $memoryAfterFormatting = memory_get_usage(true);

        $creationMemory = $memoryAfterCreation - $memoryBefore;
        $formattingMemory = $memoryAfterFormatting - $memoryAfterCreation;

        // Memory usage should be reasonable (less than 50MB for 1000 logs)
        $this->assertLessThan(50 * 1024 * 1024, $formattingMemory, 'Formatting should use less than 50MB for 1000 logs');
        
        // Formatting shouldn't use significantly more memory than the original data
        $this->assertLessThan($creationMemory * 3, $formattingMemory, 'Formatting memory should not exceed 3x original data size');

        $this->assertIsArray($result);
        $this->assertCount(1000, $result['logs']);
    }

    /** @test */
    public function it_performs_batch_formatting_efficiently()
    {
        $logs = $this->createLogCollection(200);

        // Test batch formatting vs individual formatting
        $startTime = microtime(true);
        $batchResult = $this->formatter->formatLogCollection($logs);
        $batchTime = microtime(true) - $startTime;

        $startTime = microtime(true);
        $individualResults = [];
        foreach ($logs as $log) {
            $individualResults[] = $this->formatter->formatSingleLog($log);
        }
        $individualTime = microtime(true) - $startTime;

        // Batch processing should be more efficient
        $this->assertLessThan($individualTime, $batchTime, 'Batch formatting should be faster than individual formatting');
        $this->assertCount(200, $batchResult['logs']);
        $this->assertCount(200, $individualResults);
    }

    /** @test */
    public function it_handles_concurrent_formatting_requests()
    {
        $logs = $this->createLogCollection(50);

        // Simulate concurrent requests
        $results = [];
        $startTime = microtime(true);
        
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->formatter->formatLogCollection($logs, [
                'include_metadata' => ($i % 2 === 0)
            ]);
        }
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        $averageTime = $totalTime / 10;

        $this->assertCount(10, $results);
        $this->assertLessThan(100, $averageTime, 'Average concurrent formatting should take less than 100ms');

        // Verify all results are valid
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('logs', $result);
            $this->assertCount(50, $result['logs']);
        }
    }

    /** @test */
    public function it_performs_repository_formatted_queries_efficiently()
    {
        // Create test logs in database
        $this->createDatabaseTestLogs(100);

        $startTime = microtime(true);
        $result = $this->repository->findWithFormattedResponse(
            ['action' => TaskLog::ACTION_CREATED],
            null,
            null,
            'created_at',
            'desc',
            25,
            0,
            ['include_metadata' => true]
        );
        $queryTime = (microtime(true) - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertLessThan(500, $queryTime, 'Database query with formatting should take less than 500ms');
    }

    /** @test */
    public function it_measures_statistics_generation_performance()
    {
        $this->createDatabaseTestLogs(200);

        $startTime = microtime(true);
        $result = $this->repository->getStatisticsWithFormattedResponse(
            Carbon::now()->subDays(7),
            Carbon::now(),
            ['detailed_breakdown' => true]
        );
        $statsTime = (microtime(true) - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertLessThan(1000, $statsTime, 'Statistics generation should take less than 1 second');
    }

    /** @test */
    public function it_tests_formatting_with_complex_data_structures()
    {
        $complexLogs = $this->createLogsWithComplexData(50);

        $startTime = microtime(true);
        $result = $this->formatter->formatLogCollection($complexLogs);
        $complexTime = (microtime(true) - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertCount(50, $result['logs']);
        $this->assertLessThan(300, $complexTime, 'Complex data formatting should take less than 300ms');

        // Verify complex data is properly formatted
        $firstLog = $result['logs'][0];
        $this->assertArrayHasKey('old_data', $firstLog);
        $this->assertArrayHasKey('new_data', $firstLog);
        $this->assertIsArray($firstLog['old_data']);
        $this->assertIsArray($firstLog['new_data']);
    }

    /** @test */
    public function it_measures_different_configuration_impact_on_performance()
    {
        $logs = $this->createLogCollection(100);

        // Test minimal configuration
        $startTime = microtime(true);
        $minimalResult = $this->formatter->formatLogCollection($logs, [
            'include_metadata' => false,
            'include_technical' => false,
            'include_user_details' => false
        ]);
        $minimalTime = microtime(true) - $startTime;

        // Test full configuration
        $startTime = microtime(true);
        $fullResult = $this->formatter->formatLogCollection($logs, [
            'include_metadata' => true,
            'include_technical' => true,
            'include_user_details' => true
        ]);
        $fullTime = microtime(true) - $startTime;

        $this->assertIsArray($minimalResult);
        $this->assertIsArray($fullResult);
        $this->assertCount(100, $minimalResult['logs']);
        $this->assertCount(100, $fullResult['logs']);

        // Full formatting should take longer but not excessively
        $this->assertGreaterThan($minimalTime, $fullTime, 'Full formatting should take longer than minimal');
        $this->assertLessThan($minimalTime * 5, $fullTime, 'Full formatting should not be more than 5x slower');
    }

    /** @test */
    public function it_tests_cache_effectiveness_simulation()
    {
        $log = $this->createTestLog();

        // Simulate first formatting (cache miss)
        $startTime = microtime(true);
        $firstResult = $this->formatter->formatSingleLog($log);
        $firstTime = microtime(true) - $startTime;

        // Simulate subsequent formatting (cache hit simulation)
        $startTime = microtime(true);
        $secondResult = $this->formatter->formatSingleLog($log);
        $secondTime = microtime(true) - $startTime;

        $this->assertIsArray($firstResult);
        $this->assertIsArray($secondResult);
        
        // Results should be identical
        $this->assertEquals($firstResult['id'], $secondResult['id']);
        $this->assertEquals($firstResult['task_id'], $secondResult['task_id']);

        // Second call might be faster due to various optimizations
        // This is mainly to ensure repeated calls don't degrade performance
        $this->assertLessThan($firstTime * 2, $secondTime, 'Repeated formatting should not be significantly slower');
    }

    /**
     * Helper methods for creating test data
     */
    protected function createTestLog(): TaskLog
    {
        $log = new TaskLog([
            'task_id' => 100,
            'action' => TaskLog::ACTION_CREATED,
            'new_data' => ['title' => 'Test Task'],
            'user_id' => 1,
            'user_name' => 'Test User',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        $log->_id = new ObjectId();
        return $log;
    }

    protected function createLogCollection(int $count): \Illuminate\Support\Collection
    {
        $logs = collect();
        $actions = [TaskLog::ACTION_CREATED, TaskLog::ACTION_UPDATED, TaskLog::ACTION_DELETED];

        for ($i = 1; $i <= $count; $i++) {
            $log = new TaskLog([
                'task_id' => 100 + $i,
                'action' => $actions[$i % 3],
                'new_data' => ['title' => "Test Task {$i}"],
                'user_id' => ($i % 5) + 1,
                'user_name' => "User " . (($i % 5) + 1),
                'created_at' => Carbon::now()->subMinutes($i),
                'updated_at' => Carbon::now()->subMinutes($i)
            ]);
            $log->_id = new ObjectId();
            $logs->push($log);
        }

        return $logs;
    }

    protected function createLogsWithComplexData(int $count): \Illuminate\Support\Collection
    {
        $logs = collect();

        for ($i = 1; $i <= $count; $i++) {
            $complexOldData = [
                'basic_info' => [
                    'title' => "Complex Task {$i}",
                    'description' => str_repeat("Description for task {$i}. ", 10),
                    'priority' => 'high',
                    'status' => 'pending'
                ],
                'metadata' => [
                    'tags' => ['tag1', 'tag2', 'tag3'],
                    'assignees' => [
                        ['id' => 1, 'name' => 'User 1', 'role' => 'developer'],
                        ['id' => 2, 'name' => 'User 2', 'role' => 'manager']
                    ],
                    'custom_fields' => array_fill_keys(
                        ['field1', 'field2', 'field3', 'field4', 'field5'],
                        'Custom value ' . $i
                    )
                ],
                'history' => array_fill(0, 10, [
                    'timestamp' => Carbon::now()->subDays($i % 7)->toISOString(),
                    'action' => 'updated',
                    'user' => "User {$i}"
                ])
            ];

            $complexNewData = $complexOldData;
            $complexNewData['basic_info']['status'] = 'in_progress';
            $complexNewData['metadata']['tags'][] = 'updated';

            $log = new TaskLog([
                'task_id' => 1000 + $i,
                'action' => TaskLog::ACTION_UPDATED,
                'old_data' => $complexOldData,
                'new_data' => $complexNewData,
                'user_id' => ($i % 3) + 1,
                'user_name' => "User " . (($i % 3) + 1),
                'created_at' => Carbon::now()->subMinutes($i * 2),
                'updated_at' => Carbon::now()->subMinutes($i * 2)
            ]);
            $log->_id = new ObjectId();
            $logs->push($log);
        }

        return $logs;
    }

    protected function generateLargeStatisticsData(): array
    {
        return [
            'total_logs' => 10000,
            'action_distribution' => [
                TaskLog::ACTION_CREATED => 4000,
                TaskLog::ACTION_UPDATED => 3500,
                TaskLog::ACTION_DELETED => 2000,
                TaskLog::ACTION_RESTORED => 500
            ],
            'user_distribution' => array_fill_keys(
                range(1, 100),
                rand(50, 200)
            ),
            'daily_activity' => array_fill_keys(
                array_map(fn($i) => Carbon::now()->subDays($i)->format('Y-m-d'), range(0, 30)),
                rand(100, 500)
            ),
            'hourly_activity' => array_fill_keys(
                range(0, 23),
                rand(50, 200)
            )
        ];
    }

    protected function createDatabaseTestLogs(int $count): void
    {
        $actions = [TaskLog::ACTION_CREATED, TaskLog::ACTION_UPDATED, TaskLog::ACTION_DELETED];

        for ($i = 1; $i <= $count; $i++) {
            TaskLog::create([
                'task_id' => 2000 + $i,
                'action' => $actions[$i % 3],
                'new_data' => ['title' => "Database Test Task {$i}"],
                'user_id' => ($i % 5) + 1,
                'user_name' => "DB User " . (($i % 5) + 1),
                'created_at' => Carbon::now()->subMinutes($i),
                'updated_at' => Carbon::now()->subMinutes($i)
            ]);
        }
    }
}