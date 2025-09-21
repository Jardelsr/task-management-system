<?php

namespace Tests\Unit;

use App\Services\LogResponseFormatter;
use App\Models\TaskLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;

class LogResponseFormatterTest extends TestCase
{
    use DatabaseTransactions;

    protected LogResponseFormatter $formatter;
    protected TaskLog $sampleLog;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->formatter = new LogResponseFormatter();
        
        // Create a sample TaskLog for testing
        $this->sampleLog = new TaskLog([
            '_id' => '507f1f77bcf86cd799439011',
            'task_id' => 123,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => ['title' => 'Old Title', 'status' => 'pending'],
            'new_data' => ['title' => 'New Title', 'status' => 'in_progress'],
            'user_id' => 456,
            'user_name' => 'John Doe',
            'description' => 'Task updated by user',
            'created_at' => Carbon::parse('2024-01-15 10:30:00'),
            'updated_at' => Carbon::parse('2024-01-15 10:30:00')
        ]);
        $this->sampleLog->exists = true;
    }

    /** @test */
    public function it_formats_single_log_with_default_options()
    {
        $formatted = $this->formatter->formatSingleLog($this->sampleLog);

        $this->assertIsArray($formatted);
        $this->assertEquals('507f1f77bcf86cd799439011', $formatted['id']);
        $this->assertEquals(123, $formatted['task_id']);
        $this->assertEquals('updated', $formatted['action']);
        $this->assertEquals('Updated', $formatted['action_display']);
        $this->assertEquals(456, $formatted['user_id']);
        $this->assertEquals('John Doe', $formatted['user_name']);
        $this->assertArrayHasKey('old_data', $formatted);
        $this->assertArrayHasKey('new_data', $formatted);
        $this->assertArrayHasKey('created_at', $formatted);
        $this->assertArrayHasKey('meta', $formatted);
    }

    /** @test */
    public function it_formats_single_log_with_custom_date_format()
    {
        $formatted = $this->formatter->formatSingleLog($this->sampleLog, [
            'date_format' => 'human'
        ]);

        $this->assertStringContainsString('ago', $formatted['created_at']);
        $this->assertStringContainsString('ago', $formatted['updated_at']);
    }

    /** @test */
    public function it_formats_single_log_without_metadata()
    {
        $formatted = $this->formatter->formatSingleLog($this->sampleLog, [
            'include_metadata' => false
        ]);

        $this->assertArrayNotHasKey('meta', $formatted);
    }

    /** @test */
    public function it_includes_change_summary_for_update_logs()
    {
        $formatted = $this->formatter->formatSingleLog($this->sampleLog);

        $this->assertArrayHasKey('meta', $formatted);
        $this->assertArrayHasKey('change_summary', $formatted['meta']);
        $this->assertIsArray($formatted['meta']['change_summary']);
        $this->assertCount(2, $formatted['meta']['change_summary']); // title and status changed
    }

    /** @test */
    public function it_formats_created_log_without_old_data()
    {
        $createdLog = new TaskLog([
            '_id' => '507f1f77bcf86cd799439012',
            'task_id' => 124,
            'action' => TaskLog::ACTION_CREATED,
            'old_data' => null,
            'new_data' => ['title' => 'New Task', 'status' => 'pending'],
            'user_id' => 456,
            'user_name' => 'John Doe',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        $createdLog->exists = true;

        $formatted = $this->formatter->formatSingleLog($createdLog);

        $this->assertNull($formatted['old_data']);
        $this->assertIsArray($formatted['new_data']);
        $this->assertEquals('created', $formatted['action']);
        $this->assertEquals('Created', $formatted['action_display']);
    }

    /** @test */
    public function it_formats_deleted_log_without_new_data()
    {
        $deletedLog = new TaskLog([
            '_id' => '507f1f77bcf86cd799439013',
            'task_id' => 125,
            'action' => TaskLog::ACTION_DELETED,
            'old_data' => ['title' => 'Deleted Task', 'status' => 'completed'],
            'new_data' => null,
            'user_id' => 456,
            'user_name' => 'John Doe',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        $deletedLog->exists = true;

        $formatted = $this->formatter->formatSingleLog($deletedLog);

        $this->assertIsArray($formatted['old_data']);
        $this->assertNull($formatted['new_data']);
        $this->assertEquals('deleted', $formatted['action']);
        $this->assertEquals('Deleted', $formatted['action_display']);
    }

    /** @test */
    public function it_formats_log_collection()
    {
        $logs = collect([
            $this->sampleLog,
            new TaskLog([
                '_id' => '507f1f77bcf86cd799439014',
                'task_id' => 126,
                'action' => TaskLog::ACTION_CREATED,
                'new_data' => ['title' => 'Another Task'],
                'user_id' => 789,
                'user_name' => 'Jane Smith',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ])
        ]);

        $logs->each(function ($log) {
            $log->exists = true;
        });

        $formatted = $this->formatter->formatLogCollection($logs);

        $this->assertArrayHasKey('logs', $formatted);
        $this->assertArrayHasKey('meta', $formatted);
        $this->assertCount(2, $formatted['logs']);
        $this->assertEquals(2, $formatted['meta']['total_returned']);
        $this->assertArrayHasKey('action_distribution', $formatted['meta']);
    }

    /** @test */
    public function it_formats_paginated_logs()
    {
        $logs = collect([$this->sampleLog]);
        $logs->each(function ($log) {
            $log->exists = true;
        });

        $pagination = [
            'current_page' => 1,
            'per_page' => 50,
            'total' => 100,
            'last_page' => 2,
            'from' => 1,
            'to' => 50,
            'has_next_page' => true,
            'has_previous_page' => false
        ];

        $formatted = $this->formatter->formatPaginatedLogs($logs, $pagination);

        $this->assertArrayHasKey('logs', $formatted);
        $this->assertArrayHasKey('pagination', $formatted);
        $this->assertArrayHasKey('meta', $formatted);
        
        $this->assertEquals(1, $formatted['pagination']['current_page']);
        $this->assertEquals(50, $formatted['pagination']['per_page']);
        $this->assertEquals(100, $formatted['pagination']['total']);
        $this->assertTrue($formatted['pagination']['has_next_page']);
        $this->assertFalse($formatted['pagination']['has_previous_page']);
        $this->assertArrayHasKey('links', $formatted['pagination']);
    }

    /** @test */
    public function it_formats_log_statistics()
    {
        $statistics = [
            'total_logs' => 150,
            'logs_by_action' => [
                'created' => 50,
                'updated' => 80,
                'deleted' => 20
            ],
            'recent_activity' => [
                'created' => 5,
                'updated' => 10,
                'deleted' => 2
            ],
            'date_range' => [
                'from' => '2024-01-01T00:00:00.000Z',
                'to' => '2024-01-31T23:59:59.000Z'
            ]
        ];

        $formatted = $this->formatter->formatLogStatistics($statistics);

        $this->assertEquals(150, $formatted['total_logs']);
        $this->assertArrayHasKey('logs_by_action', $formatted);
        $this->assertArrayHasKey('recent_activity', $formatted);
        $this->assertArrayHasKey('date_range', $formatted);
        $this->assertArrayHasKey('meta', $formatted);

        // Check action statistics formatting
        $this->assertIsArray($formatted['logs_by_action']);
        $createdStat = collect($formatted['logs_by_action'])->firstWhere('action', 'created');
        $this->assertEquals(50, $createdStat['count']);
        $this->assertEquals('Created', $createdStat['action_display']);
        $this->assertGreaterThan(0, $createdStat['percentage']);
    }

    /** @test */
    public function it_handles_different_date_formats()
    {
        $dateFormats = ['iso8601', 'timestamp', 'human', 'date_only', 'datetime'];

        foreach ($dateFormats as $format) {
            $formatted = $this->formatter->formatSingleLog($this->sampleLog, [
                'date_format' => $format,
                'include_metadata' => false
            ]);

            $this->assertNotNull($formatted['created_at'], "Date format '{$format}' should not return null");
            
            switch ($format) {
                case 'iso8601':
                    $this->assertStringContainsString('T', $formatted['created_at']);
                    $this->assertStringContainsString('Z', $formatted['created_at']);
                    break;
                case 'timestamp':
                    $this->assertIsString($formatted['created_at']);
                    $this->assertIsNumeric($formatted['created_at']);
                    break;
                case 'human':
                    $this->assertStringContainsString('ago', $formatted['created_at']);
                    break;
                case 'date_only':
                    $this->assertStringNotContainsString('T', $formatted['created_at']);
                    $this->assertRegExp('/^\d{4}-\d{2}-\d{2}$/', $formatted['created_at']);
                    break;
                case 'datetime':
                    $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $formatted['created_at']);
                    break;
            }
        }
    }

    /** @test */
    public function it_handles_system_actions_without_user()
    {
        $systemLog = new TaskLog([
            '_id' => '507f1f77bcf86cd799439015',
            'task_id' => 127,
            'action' => TaskLog::ACTION_CREATED,
            'new_data' => ['title' => 'System Task'],
            'user_id' => null,
            'user_name' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        $systemLog->exists = true;

        $formatted = $this->formatter->formatSingleLog($systemLog);

        $this->assertNull($formatted['user_id']);
        $this->assertEquals('System', $formatted['user_name']);
        $this->assertArrayHasKey('meta', $formatted);
        $this->assertTrue($formatted['meta']['is_system_action']);
    }

    /** @test */
    public function it_calculates_log_age_correctly()
    {
        $oldLog = new TaskLog([
            '_id' => '507f1f77bcf86cd799439016',
            'task_id' => 128,
            'action' => TaskLog::ACTION_CREATED,
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subHours(2)
        ]);
        $oldLog->exists = true;

        $formatted = $this->formatter->formatSingleLog($oldLog);

        $this->assertStringContainsString('2 hours ago', $formatted['meta']['log_age']);
    }

    /** @test */
    public function it_generates_proper_change_summary_for_updates()
    {
        $updateLog = new TaskLog([
            '_id' => '507f1f77bcf86cd799439017',
            'task_id' => 129,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => [
                'title' => 'Old Title',
                'status' => 'pending',
                'assignee' => 'John'
            ],
            'new_data' => [
                'title' => 'New Title',
                'status' => 'in_progress',
                'assignee' => 'Jane'
            ],
            'user_id' => 456,
            'user_name' => 'Admin',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        $updateLog->exists = true;

        $formatted = $this->formatter->formatSingleLog($updateLog);

        $this->assertArrayHasKey('change_summary', $formatted['meta']);
        $changeSummary = $formatted['meta']['change_summary'];
        
        $this->assertCount(3, $changeSummary); // title, status, assignee changed
        
        $titleChange = collect($changeSummary)->firstWhere('field', 'title');
        $this->assertEquals('Old Title', $titleChange['from']);
        $this->assertEquals('New Title', $titleChange['to']);
        $this->assertEquals('modified', $titleChange['change_type']);
    }

    /** @test */
    public function it_handles_empty_collections()
    {
        $emptyLogs = collect([]);
        $formatted = $this->formatter->formatLogCollection($emptyLogs);

        $this->assertArrayHasKey('logs', $formatted);
        $this->assertArrayHasKey('meta', $formatted);
        $this->assertCount(0, $formatted['logs']);
        $this->assertEquals(0, $formatted['meta']['total_returned']);
    }

    /** @test */
    public function it_includes_technical_metadata_when_requested()
    {
        $formatted = $this->formatter->formatSingleLog($this->sampleLog, [
            'include_technical' => true
        ]);

        $this->assertArrayHasKey('meta', $formatted);
        $this->assertArrayHasKey('technical', $formatted['meta']);
        $this->assertArrayHasKey('collection', $formatted['meta']['technical']);
        $this->assertArrayHasKey('mongo_id', $formatted['meta']['technical']);
        $this->assertArrayHasKey('document_size', $formatted['meta']['technical']);
    }

    /** @test */
    public function it_excludes_technical_metadata_by_default()
    {
        $formatted = $this->formatter->formatSingleLog($this->sampleLog);

        $this->assertArrayHasKey('meta', $formatted);
        $this->assertArrayNotHasKey('technical', $formatted['meta']);
    }

    /** @test */
    public function it_formats_collection_metadata_properly()
    {
        $logs = collect([
            $this->sampleLog,
            new TaskLog([
                '_id' => '507f1f77bcf86cd799439018',
                'task_id' => 130,
                'action' => TaskLog::ACTION_CREATED,
                'created_at' => Carbon::now()->subHour(),
                'updated_at' => Carbon::now()->subHour()
            ]),
            new TaskLog([
                '_id' => '507f1f77bcf86cd799439019',
                'task_id' => 131,
                'action' => TaskLog::ACTION_DELETED,
                'created_at' => Carbon::now()->subMinutes(30),
                'updated_at' => Carbon::now()->subMinutes(30)
            ])
        ]);

        $logs->each(function ($log) {
            $log->exists = true;
        });

        $formatted = $this->formatter->formatLogCollection($logs);

        $this->assertEquals(3, $formatted['meta']['total_returned']);
        $this->assertEquals('log_collection', $formatted['meta']['data_type']);
        
        $actionDistribution = $formatted['meta']['action_distribution'];
        $this->assertEquals(1, $actionDistribution['updated']);
        $this->assertEquals(1, $actionDistribution['created']);
        $this->assertEquals(1, $actionDistribution['deleted']);
        
        $this->assertArrayHasKey('date_range', $formatted['meta']);
        $this->assertNotNull($formatted['meta']['date_range']['oldest']);
        $this->assertNotNull($formatted['meta']['date_range']['newest']);
    }
}