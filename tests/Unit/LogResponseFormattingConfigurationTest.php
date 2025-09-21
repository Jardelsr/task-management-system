<?php

namespace Tests\Unit;

use App\Services\LogResponseFormatter;
use App\Models\TaskLog;
use Carbon\Carbon;
use Tests\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;

class LogResponseFormattingConfigurationTest extends TestCase
{
    use DatabaseTransactions;

    protected LogResponseFormatter $formatter;
    protected TaskLog $testLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new LogResponseFormatter();
        $this->testLog = $this->createTestLog();
    }

    /** @test */
    public function it_respects_default_fields_configuration()
    {
        // Test with default configuration
        Config::set('log_responses.default_fields', [
            'id', 'task_id', 'action', 'timestamp'
        ]);

        $result = $this->formatter->formatSingleLog($this->testLog);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('task_id', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        // Should not include fields not in default list
        $this->assertArrayNotHasKey('old_data', $result);
        $this->assertArrayNotHasKey('new_data', $result);
    }

    /** @test */
    public function it_includes_all_fields_when_configured()
    {
        Config::set('log_responses.default_fields', [
            'id', 'task_id', 'action', 'action_display', 'user', 'timestamp', 
            'old_data', 'new_data', 'changes', 'technical'
        ]);

        $result = $this->formatter->formatSingleLog($this->testLog, [
            'include_technical' => true
        ]);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('task_id', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('action_display', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('old_data', $result);
        $this->assertArrayHasKey('new_data', $result);
        $this->assertArrayHasKey('changes', $result);
        $this->assertArrayHasKey('technical', $result);
    }

    /** @test */
    public function it_respects_date_format_configuration()
    {
        // Test ISO8601 format
        Config::set('log_responses.date_formats.default', 'iso8601');
        Config::set('log_responses.date_formats.include_human', true);

        $result = $this->formatter->formatSingleLog($this->testLog);
        
        $this->assertArrayHasKey('iso8601', $result['timestamp']);
        $this->assertArrayHasKey('human', $result['timestamp']);
        $this->assertArrayHasKey('timezone', $result['timestamp']);

        // Test simple format
        Config::set('log_responses.date_formats.default', 'simple');
        Config::set('log_responses.date_formats.include_human', false);

        $result = $this->formatter->formatSingleLog($this->testLog);
        
        $this->assertIsString($result['timestamp']);
        $this->assertStringContainsString('2024', $result['timestamp']); // Simple string format
    }

    /** @test */
    public function it_respects_user_information_configuration()
    {
        // Test detailed user info
        Config::set('log_responses.user_information.include_detailed', true);
        Config::set('log_responses.user_information.show_system_users', true);

        $result = $this->formatter->formatSingleLog($this->testLog);

        $user = $result['user'];
        $this->assertArrayHasKey('id', $user);
        $this->assertArrayHasKey('name', $user);
        $this->assertArrayHasKey('type', $user);
        $this->assertArrayHasKey('is_system', $user);

        // Test minimal user info
        Config::set('log_responses.user_information.include_detailed', false);
        
        $result = $this->formatter->formatSingleLog($this->testLog);

        $user = $result['user'];
        $this->assertIsString($user); // Should be just the name as string
    }

    /** @test */
    public function it_respects_action_display_configuration()
    {
        Config::set('log_responses.action_display.show_display_name', true);
        Config::set('log_responses.action_display.capitalize', true);

        $result = $this->formatter->formatSingleLog($this->testLog);

        $this->assertArrayHasKey('action_display', $result);
        $this->assertEquals('Created', $result['action_display']);
        
        // Test without display name
        Config::set('log_responses.action_display.show_display_name', false);
        
        $result = $this->formatter->formatSingleLog($this->testLog);
        $this->assertArrayNotHasKey('action_display', $result);
    }

    /** @test */
    public function it_respects_data_formatting_configuration()
    {
        // Create log with data
        $logWithData = $this->createTestLogWithData();

        // Test detailed data formatting
        Config::set('log_responses.data_formatting.include_old_data', true);
        Config::set('log_responses.data_formatting.include_new_data', true);
        Config::set('log_responses.data_formatting.show_changes_summary', true);

        $result = $this->formatter->formatSingleLog($logWithData);

        $this->assertArrayHasKey('old_data', $result);
        $this->assertArrayHasKey('new_data', $result);
        $this->assertArrayHasKey('changes', $result);

        // Test minimal data formatting
        Config::set('log_responses.data_formatting.include_old_data', false);
        Config::set('log_responses.data_formatting.include_new_data', false);
        Config::set('log_responses.data_formatting.show_changes_summary', false);

        $result = $this->formatter->formatSingleLog($logWithData);

        $this->assertArrayNotHasKey('old_data', $result);
        $this->assertArrayNotHasKey('new_data', $result);
        $this->assertArrayNotHasKey('changes', $result);
    }

    /** @test */
    public function it_respects_metadata_configuration()
    {
        // Test with metadata enabled
        Config::set('log_responses.metadata.include_query_info', true);
        Config::set('log_responses.metadata.include_performance', true);
        Config::set('log_responses.metadata.include_formatting_info', true);

        $logs = collect([$this->testLog]);
        $result = $this->formatter->formatLogCollection($logs, [
            'include_metadata' => true
        ]);

        $this->assertArrayHasKey('meta', $result);
        $meta = $result['meta'];
        $this->assertArrayHasKey('generated_at', $meta);
        $this->assertArrayHasKey('formatting_applied', $meta);

        // Test with metadata disabled
        Config::set('log_responses.metadata.include_query_info', false);
        Config::set('log_responses.metadata.include_performance', false);
        Config::set('log_responses.metadata.include_formatting_info', false);

        $result = $this->formatter->formatLogCollection($logs, [
            'include_metadata' => true
        ]);

        // Should have minimal metadata
        $this->assertArrayHasKey('meta', $result);
        $meta = $result['meta'];
        $this->assertArrayHasKey('generated_at', $meta); // Always included
    }

    /** @test */
    public function it_respects_pagination_configuration()
    {
        Config::set('log_responses.pagination.include_page_info', true);
        Config::set('log_responses.pagination.include_navigation_urls', false);
        Config::set('log_responses.pagination.show_total_pages', true);

        $logs = collect([$this->testLog]);
        $result = $this->formatter->formatPaginatedLogs(
            $logs,
            25,  // total
            10,  // per_page
            0,   // offset
            []   // options
        );

        $pagination = $result['pagination'];
        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('total_pages', $pagination);
        $this->assertArrayHasKey('has_next_page', $pagination);
        $this->assertArrayHasKey('has_previous_page', $pagination);

        // Should not include navigation URLs
        $this->assertArrayNotHasKey('next_page_url', $pagination);
        $this->assertArrayNotHasKey('previous_page_url', $pagination);
    }

    /** @test */
    public function it_respects_statistics_configuration()
    {
        // Create multiple logs for statistics
        $this->createMultipleTestLogs();

        Config::set('log_responses.statistics.include_distribution', true);
        Config::set('log_responses.statistics.include_user_stats', true);
        Config::set('log_responses.statistics.include_date_analysis', true);

        $result = $this->formatter->formatLogStatistics([
            'total_logs' => 10,
            'action_distribution' => [
                TaskLog::ACTION_CREATED => 5,
                TaskLog::ACTION_UPDATED => 3,
                TaskLog::ACTION_DELETED => 2
            ]
        ], [
            'detailed_breakdown' => true
        ]);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('actions', $result);
        
        // Check actions breakdown has detailed information
        $actions = $result['actions'];
        $this->assertArrayHasKey('breakdown', $actions);
        $this->assertArrayHasKey('total_by_type', $actions);

        if (!empty($actions['breakdown'])) {
            $firstAction = $actions['breakdown'][0];
            $this->assertArrayHasKey('action_display', $firstAction);
            $this->assertArrayHasKey('percentage', $firstAction);
        }
    }

    /** @test */
    public function it_respects_security_configuration()
    {
        // Test with sensitive data masking enabled
        Config::set('log_responses.security.mask_sensitive_data', true);
        Config::set('log_responses.security.sensitive_fields', ['password', 'token', 'api_key']);

        $logWithSensitiveData = TaskLog::create([
            'task_id' => 999,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => [
                'title' => 'Test Task',
                'password' => 'secret123',
                'api_key' => 'abc123xyz'
            ],
            'new_data' => [
                'title' => 'Updated Task',
                'password' => 'newsecret456',
                'api_key' => 'def456uvw'
            ],
            'user_id' => 1,
            'user_name' => 'Test User',
            'created_at' => Carbon::now()
        ]);

        $result = $this->formatter->formatSingleLog($logWithSensitiveData);

        // Check that sensitive data is masked
        if (isset($result['old_data'])) {
            $this->assertEquals('***MASKED***', $result['old_data']['password']);
            $this->assertEquals('***MASKED***', $result['old_data']['api_key']);
            $this->assertEquals('Test Task', $result['old_data']['title']); // Non-sensitive field preserved
        }

        if (isset($result['new_data'])) {
            $this->assertEquals('***MASKED***', $result['new_data']['password']);
            $this->assertEquals('***MASKED***', $result['new_data']['api_key']);
            $this->assertEquals('Updated Task', $result['new_data']['title']);
        }
    }

    /** @test */
    public function it_handles_performance_configuration()
    {
        Config::set('log_responses.performance.enable_caching', true);
        Config::set('log_responses.performance.cache_duration', 300);
        Config::set('log_responses.performance.lazy_load_relations', true);

        $logs = collect([$this->testLog]);
        
        // This test mainly ensures configuration is read without errors
        // Real performance testing would require more complex setup
        $result = $this->formatter->formatLogCollection($logs, [
            'include_metadata' => true
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
    }

    /** @test */
    public function it_uses_default_configuration_when_options_not_provided()
    {
        // Reset to default configuration
        Config::set('log_responses', config('log_responses'));

        $result = $this->formatter->formatSingleLog($this->testLog);

        // Should use default configuration values
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('action', $result);
        
        // Check default date format
        if (is_array($result['timestamp'])) {
            $this->assertArrayHasKey('iso8601', $result['timestamp']);
        }
    }

    /** @test */
    public function it_merges_options_with_configuration_correctly()
    {
        Config::set('log_responses.user_information.include_detailed', false);
        
        // Override configuration with options
        $result = $this->formatter->formatSingleLog($this->testLog, [
            'include_user_details' => true
        ]);

        // Options should override configuration
        $this->assertIsArray($result['user']);
        $this->assertArrayHasKey('id', $result['user']);
        $this->assertArrayHasKey('name', $result['user']);
    }

    /**
     * Helper methods for creating test data
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

    protected function createTestLogWithData(): TaskLog
    {
        return TaskLog::create([
            'task_id' => 200,
            'action' => TaskLog::ACTION_UPDATED,
            'old_data' => [
                'title' => 'Old Title',
                'status' => 'pending',
                'priority' => 'low'
            ],
            'new_data' => [
                'title' => 'New Title',
                'status' => 'in_progress',
                'priority' => 'high'
            ],
            'user_id' => 2,
            'user_name' => 'Updater',
            'created_at' => Carbon::now()
        ]);
    }

    protected function createMultipleTestLogs(): void
    {
        $actions = [TaskLog::ACTION_CREATED, TaskLog::ACTION_UPDATED, TaskLog::ACTION_DELETED];
        
        for ($i = 1; $i <= 10; $i++) {
            TaskLog::create([
                'task_id' => 300 + $i,
                'action' => $actions[$i % 3],
                'user_id' => ($i % 2) + 1,
                'user_name' => "User " . (($i % 2) + 1),
                'created_at' => Carbon::now()->subMinutes($i)
            ]);
        }
    }
}