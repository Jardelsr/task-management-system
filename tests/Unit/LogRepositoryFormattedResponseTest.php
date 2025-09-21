<?php

namespace Tests\Unit;

use App\Repositories\LogRepository;
use App\Models\TaskLog;
use Carbon\Carbon;
use Tests\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;

class LogRepositoryFormattedResponseTest extends TestCase
{
    use DatabaseTransactions;

    protected LogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new LogRepository();
        
        // Set up MongoDB connection for testing
        $this->artisan('config:cache');
    }

    /** @test */
    public function it_finds_logs_with_formatted_response()
    {
        // Create test logs
        $this->createTestLogs();

        $criteria = ['action' => TaskLog::ACTION_CREATED];
        $result = $this->repository->findWithFormattedResponse(
            $criteria,
            null,
            null,
            'created_at',
            'desc',
            10,
            0,
            ['include_metadata' => true]
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertArrayHasKey('applied_filters', $result);
        $this->assertArrayHasKey('query_metadata', $result);

        // Check pagination structure
        $pagination = $result['pagination'];
        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('has_next_page', $pagination);

        // Check applied filters
        $this->assertEquals(TaskLog::ACTION_CREATED, $result['applied_filters']['action']);

        // Check statistics
        $this->assertArrayHasKey('total_found', $result['statistics']);
        $this->assertArrayHasKey('action_distribution', $result['statistics']);
    }

    /** @test */
    public function it_finds_single_log_by_id_with_formatted_response()
    {
        $log = $this->createSingleTestLog();
        
        $result = $this->repository->findByIdWithFormattedResponse(
            (string) $log->_id,
            ['include_metadata' => true, 'include_technical' => true]
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('log', $result);
        $this->assertArrayHasKey('meta', $result);

        $formattedLog = $result['log'];
        $this->assertEquals((string) $log->_id, $formattedLog['id']);
        $this->assertEquals($log->task_id, $formattedLog['task_id']);
        $this->assertEquals($log->action, $formattedLog['action']);
        $this->assertArrayHasKey('action_display', $formattedLog);

        // Check metadata
        $this->assertArrayHasKey('retrieved_at', $result['meta']);
        $this->assertArrayHasKey('log_id', $result['meta']);
    }

    /** @test */
    public function it_returns_null_for_non_existent_log_id()
    {
        $result = $this->repository->findByIdWithFormattedResponse('507f1f77bcf86cd799439999');
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_finds_task_logs_with_formatted_response()
    {
        $logs = $this->createTestLogsForTask(123);
        
        $result = $this->repository->findByTaskWithFormattedResponse(
            123,
            50,
            ['include_metadata' => true]
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('task_metadata', $result);

        // Check task metadata
        $taskMeta = $result['task_metadata'];
        $this->assertEquals(123, $taskMeta['task_id']);
        $this->assertArrayHasKey('total_logs_for_task', $taskMeta);
        $this->assertArrayHasKey('returned_count', $taskMeta);
    }

    /** @test */
    public function it_gets_statistics_with_formatted_response()
    {
        $this->createTestLogs();

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();
        
        $result = $this->repository->getStatisticsWithFormattedResponse(
            $startDate,
            $endDate,
            ['detailed_breakdown' => true]
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertArrayHasKey('activity', $result);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertArrayHasKey('metadata', $result);

        // Check summary structure
        $summary = $result['summary'];
        $this->assertArrayHasKey('total_logs', $summary);
        $this->assertArrayHasKey('period_analyzed', $summary);

        // Check actions breakdown
        $actions = $result['actions'];
        $this->assertArrayHasKey('breakdown', $actions);
        $this->assertArrayHasKey('total_by_type', $actions);

        // Each breakdown item should have proper structure
        if (!empty($actions['breakdown'])) {
            $firstAction = $actions['breakdown'][0];
            $this->assertArrayHasKey('action', $firstAction);
            $this->assertArrayHasKey('action_display', $firstAction);
            $this->assertArrayHasKey('count', $firstAction);
            $this->assertArrayHasKey('percentage', $firstAction);
        }
    }

    /** @test */
    public function it_handles_date_range_filtering_in_formatted_response()
    {
        $this->createTestLogsWithDifferentDates();

        $startDate = Carbon::now()->subDays(5);
        $endDate = Carbon::now()->subDays(1);

        $result = $this->repository->findWithFormattedResponse(
            [],
            $startDate,
            $endDate,
            'created_at',
            'desc',
            10,
            0
        );

        $this->assertArrayHasKey('query_metadata', $result);
        $this->assertEquals($startDate->toISOString(), $result['query_metadata']['date_range']['start']);
        $this->assertEquals($endDate->toISOString(), $result['query_metadata']['date_range']['end']);

        // All returned logs should be within the date range
        foreach ($result['logs'] as $log) {
            $logDate = Carbon::parse($log['created_at']);
            $this->assertTrue($logDate->between($startDate, $endDate));
        }
    }

    /** @test */
    public function it_applies_sorting_in_formatted_response()
    {
        $this->createTestLogsWithDifferentDates();

        // Test descending sort
        $resultDesc = $this->repository->findWithFormattedResponse(
            [],
            null,
            null,
            'created_at',
            'desc',
            10,
            0
        );

        // Test ascending sort
        $resultAsc = $this->repository->findWithFormattedResponse(
            [],
            null,
            null,
            'created_at',
            'asc',
            10,
            0
        );

        $this->assertArrayHasKey('query_metadata', $resultDesc);
        $this->assertEquals('created_at', $resultDesc['query_metadata']['sort_by']);
        $this->assertEquals('desc', $resultDesc['query_metadata']['sort_order']);

        $this->assertEquals('asc', $resultAsc['query_metadata']['sort_order']);

        // Check that logs are actually sorted correctly
        if (count($resultDesc['logs']) > 1) {
            $firstLog = Carbon::parse($resultDesc['logs'][0]['created_at']);
            $secondLog = Carbon::parse($resultDesc['logs'][1]['created_at']);
            $this->assertTrue($firstLog->gte($secondLog));
        }
    }

    /** @test */
    public function it_handles_pagination_correctly_in_formatted_response()
    {
        $this->createMultipleTestLogs(25); // Create 25 test logs

        // First page
        $firstPage = $this->repository->findWithFormattedResponse(
            [],
            null,
            null,
            'created_at',
            'desc',
            10,
            0
        );

        // Second page
        $secondPage = $this->repository->findWithFormattedResponse(
            [],
            null,
            null,
            'created_at',
            'desc',
            10,
            10
        );

        // Check first page pagination
        $this->assertEquals(1, $firstPage['pagination']['current_page']);
        $this->assertEquals(10, $firstPage['pagination']['per_page']);
        $this->assertTrue($firstPage['pagination']['has_next_page']);
        $this->assertFalse($firstPage['pagination']['has_previous_page']);

        // Check second page pagination
        $this->assertEquals(2, $secondPage['pagination']['current_page']);
        $this->assertTrue($secondPage['pagination']['has_previous_page']);

        // Ensure we got different logs on different pages
        $firstPageIds = collect($firstPage['logs'])->pluck('id')->toArray();
        $secondPageIds = collect($secondPage['logs'])->pluck('id')->toArray();
        $this->assertEmpty(array_intersect($firstPageIds, $secondPageIds));
    }

    /** @test */
    public function it_builds_comprehensive_query_statistics()
    {
        $this->createTestLogsWithVariousActions();

        $result = $this->repository->findWithFormattedResponse(
            ['action' => [TaskLog::ACTION_CREATED, TaskLog::ACTION_UPDATED]],
            null,
            null,
            'created_at',
            'desc',
            5,
            0
        );

        $stats = $result['statistics'];
        $this->assertArrayHasKey('total_found', $stats);
        $this->assertArrayHasKey('returned_count', $stats);
        $this->assertArrayHasKey('filtered', $stats);
        $this->assertArrayHasKey('action_distribution', $stats);
        $this->assertArrayHasKey('user_distribution', $stats);
        $this->assertArrayHasKey('date_range', $stats);
        $this->assertArrayHasKey('data_analysis', $stats);

        $this->assertTrue($stats['filtered']); // We applied action filter
        $this->assertLessThanOrEqual(5, $stats['returned_count']);

        // Check user distribution
        $userDist = $stats['user_distribution'];
        $this->assertArrayHasKey('unique_users', $userDist);
        $this->assertArrayHasKey('system_actions', $userDist);
        $this->assertArrayHasKey('user_actions', $userDist);

        // Check data analysis
        $dataAnalysis = $stats['data_analysis'];
        $this->assertArrayHasKey('logs_with_old_data', $dataAnalysis);
        $this->assertArrayHasKey('logs_with_new_data', $dataAnalysis);
        $this->assertArrayHasKey('logs_with_changes', $dataAnalysis);
    }

    /**
     * Create multiple test logs for testing
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

        TaskLog::create([
            'task_id' => 102,
            'action' => TaskLog::ACTION_DELETED,
            'old_data' => ['title' => 'Deleted Task'],
            'user_id' => null,
            'user_name' => 'System',
            'created_at' => Carbon::now()->subMinutes(30),
            'updated_at' => Carbon::now()->subMinutes(30)
        ]);
    }

    /**
     * Create a single test log
     */
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

    /**
     * Create test logs for a specific task
     */
    protected function createTestLogsForTask(int $taskId): array
    {
        $logs = [];

        $logs[] = TaskLog::create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_CREATED,
            'new_data' => ['title' => 'Task Created'],
            'user_id' => 1,
            'user_name' => 'Creator',
            'created_at' => Carbon::now()->subHours(3)
        ]);

        $logs[] = TaskLog::create([
            'task_id' => $taskId,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => ['status' => 'pending'],
            'new_data' => ['status' => 'in_progress'],
            'user_id' => 2,
            'user_name' => 'Updater',
            'created_at' => Carbon::now()->subHours(1)
        ]);

        return $logs;
    }

    /**
     * Create test logs with different dates
     */
    protected function createTestLogsWithDifferentDates(): void
    {
        TaskLog::create([
            'task_id' => 300,
            'action' => TaskLog::ACTION_CREATED,
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(10)
        ]);

        TaskLog::create([
            'task_id' => 301,
            'action' => TaskLog::ACTION_UPDATED,
            'created_at' => Carbon::now()->subDays(3),
            'updated_at' => Carbon::now()->subDays(3)
        ]);

        TaskLog::create([
            'task_id' => 302,
            'action' => TaskLog::ACTION_DELETED,
            'created_at' => Carbon::now()->subDays(1),
            'updated_at' => Carbon::now()->subDays(1)
        ]);
    }

    /**
     * Create multiple test logs
     */
    protected function createMultipleTestLogs(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            TaskLog::create([
                'task_id' => 400 + $i,
                'action' => TaskLog::ACTION_CREATED,
                'new_data' => ['title' => "Test Task {$i}"],
                'user_id' => ($i % 3) + 1,
                'user_name' => "User " . (($i % 3) + 1),
                'created_at' => Carbon::now()->subMinutes($i * 5),
                'updated_at' => Carbon::now()->subMinutes($i * 5)
            ]);
        }
    }

    /**
     * Create test logs with various actions
     */
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
                'user_id' => ($index % 2 === 0) ? null : $index + 1,
                'user_name' => ($index % 2 === 0) ? 'System' : "User " . ($index + 1),
                'created_at' => Carbon::now()->subMinutes($index * 10),
                'updated_at' => Carbon::now()->subMinutes($index * 10)
            ], $data));
        }
    }
}