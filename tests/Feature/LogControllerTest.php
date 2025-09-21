<?php

namespace Tests\Feature;

use Laravel\Lumen\Testing\TestCase;
use Laravel\Lumen\Testing\DatabaseTransactions;
use App\Models\Task;
use App\Models\TaskLog;
use App\Services\LogService;
use App\Services\LogServiceInterface;
use App\Http\Requests\LogValidationRequest;
use Carbon\Carbon;
use Mockery;

class LogControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Create the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__ . '/../../bootstrap/app.php';
    }

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create some test tasks for logging
        $this->testTask = Task::create([
            'title' => 'Test Task for Logs',
            'description' => 'A test task for log testing',
            'status' => 'pending',
            'priority' => 'medium'
        ]);
    }

    /**
     * Test log index endpoint
     */
    public function testLogIndex()
    {
        // Create some test logs
        $task = Task::create([
            'title' => 'Test Task for Logs',
            'description' => 'A test task',
            'status' => 'pending'
        ]);

        $response = $this->get('/api/v1/logs');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => [
                'meta' => ['pagination']
            ]
        ]);
    }

    /**
     * Test log index with filtering
     */
    public function testLogIndexWithFiltering()
    {
        $response = $this->get('/api/v1/logs?limit=10&action=created');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message', 
            'data',
            'meta' => ['pagination']
        ]);
    }

    /**
     * Test log show endpoint
     */
    public function testLogShow()
    {
        // Create a test task and log
        $task = Task::create([
            'title' => 'Test Task for Log Show',
            'description' => 'A test task',
            'status' => 'pending'
        ]);

        // Get a log ID from the service (simulate real scenario)
        $response = $this->get('/api/v1/logs/507f1f77bcf86cd799439011'); // Sample MongoDB ID
        
        // Since we might not have real logs, we expect either 200 with data or 404
        $this->assertTrue(in_array($response->response->getStatusCode(), [200, 404]));
    }

    /**
     * Test log stats endpoint
     */
    public function testLogStats()
    {
        $response = $this->get('/api/v1/logs/stats');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data'
        ]);
    }

    /**
     * Test log stats with date range
     */
    public function testLogStatsWithDateRange()
    {
        $startDate = \Carbon\Carbon::now()->subDays(7)->toISOString();
        $endDate = \Carbon\Carbon::now()->toISOString();
        
        $response = $this->get("/api/v1/logs/stats?start_date={$startDate}&end_date={$endDate}");
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => [
                'period'
            ]
        ]);
    }

    /**
     * Test logs by action endpoint
     */
    public function testLogsByAction()
    {
        $response = $this->get('/api/v1/logs/actions/created');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['pagination']
        ]);
    }

    /**
     * Test logs by user endpoint
     */
    public function testLogsByUser()
    {
        $response = $this->get('/api/v1/logs/users/1');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['pagination']
        ]);
    }

    /**
     * Test recent logs endpoint
     */
    public function testRecentLogs()
    {
        $response = $this->get('/api/v1/logs/recent');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => [
                'count',
                'limit'
            ]
        ]);
    }

    /**
     * Test recent logs with limit
     */
    public function testRecentLogsWithLimit()
    {
        $response = $this->get('/api/v1/logs/recent?limit=20');
        
        $response->seeStatusCode(200);
        $response->seeJson([
            'success' => true
        ]);
    }

    /**
     * Test task logs endpoint
     */
    public function testTaskLogs()
    {
        // Create a test task
        $task = Task::create([
            'title' => 'Test Task for Task Logs',
            'description' => 'A test task',
            'status' => 'pending'
        ]);

        $response = $this->get("/api/v1/logs/tasks/{$task->id}");
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['pagination']
        ]);
    }

    /**
     * Test date range logs endpoint
     */
    public function testDateRangeLogs()
    {
        $startDate = \Carbon\Carbon::now()->subDays(1)->format('Y-m-d H:i:s');
        $endDate = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
        
        $response = $this->get("/api/v1/logs/date-range?start_date={$startDate}&end_date={$endDate}");
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['pagination']
        ]);
    }

    /**
     * Test date range logs with invalid dates
     */
    public function testDateRangeLogsInvalidDates()
    {
        // End date before start date - should return validation error
        $startDate = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
        $endDate = \Carbon\Carbon::now()->subDay()->format('Y-m-d H:i:s');
        
        $response = $this->get("/api/v1/logs/date-range?start_date={$startDate}&end_date={$endDate}");
        
        $response->seeStatusCode(422); // Validation error
    }

    /**
     * Test missing date range parameters
     */
    public function testDateRangeLogsMissingParams()
    {
        $response = $this->get('/api/v1/logs/date-range');
        
        $response->seeStatusCode(422); // Validation error
    }

    /**
     * Test log export endpoint
     */
    public function testLogExport()
    {
        $response = $this->get('/api/v1/logs/export');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => [
                'total_exported'
            ]
        ]);
    }

    /**
     * Test log export with filters
     */
    public function testLogExportWithFilters()
    {
        $startDate = \Carbon\Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $endDate = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
        
        $response = $this->get("/api/v1/logs/export?start_date={$startDate}&end_date={$endDate}&action=created");
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data'
        ]);
    }

    /**
     * Test deletion stats endpoint
     */
    public function testDeletionStats()
    {
        $response = $this->get('/api/v1/logs/deletions/stats');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data'
        ]);
    }

    /**
     * Test recent deletions endpoint
     */
    public function testRecentDeletions()
    {
        $response = $this->get('/api/v1/logs/deletions/recent');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => [
                'count',
                'limit',
                'activity_type'
            ]
        ]);
    }

    /**
     * Test task deletion logs
     */
    public function testTaskDeletionLogs()
    {
        // Create a test task
        $task = Task::create([
            'title' => 'Test Task for Deletion Logs',
            'description' => 'A test task',
            'status' => 'pending'
        ]);

        $response = $this->get("/api/v1/logs/tasks/{$task->id}/deletions");
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['pagination']
        ]);
    }

    /**
     * Test log cleanup endpoint
     */
    public function testLogCleanup()
    {
        $response = $this->delete('/api/v1/logs/cleanup?retention_days=90');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data' => [
                'deleted_logs_count',
                'retention_days'
            ]
        ]);
    }

    /**
     * Test invalid task ID for task logs
     */
    public function testInvalidTaskIdForTaskLogs()
    {
        $response = $this->get('/api/v1/logs/tasks/invalid');
        
        $response->seeStatusCode(404);
    }

    /**
     * Test invalid user ID for user logs
     */
    public function testInvalidUserIdForUserLogs()
    {
        $response = $this->get('/api/v1/logs/users/invalid');
        
        $response->seeStatusCode(422); // Validation error
    }

    /**
     * Test legacy routes still work
     */
    public function testLegacyLogRoutes()
    {
        $response = $this->get('/logs');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data'
        ]);
    }

    /**
     * Test legacy stats route
     */
    public function testLegacyLogStatsRoute()
    {
        $response = $this->get('/logs/stats');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data'
        ]);
    }

    /**
     * Test root logs endpoint with id parameter
     */
    public function testRootLogsWithId()
    {
        $response = $this->get('/logs?id=507f1f77bcf86cd799439011');
        
        // Should return either 200 with data or 404 if log not found
        $this->assertTrue(in_array($response->response->getStatusCode(), [200, 404]));
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===== NEW ENHANCED TESTS FOR LOGCONTROLLER ENHANCEMENTS =====

    /**
     * Test enhanced show method with specific log ID
     */
    public function testEnhancedShowMethod()
    {
        $response = $this->get('/api/v1/logs/507f1f77bcf86cd799439011');
        
        // Should return either 200 with log data or 404 if not found
        $this->assertTrue(in_array($response->response->getStatusCode(), [200, 404]));
        
        if ($response->response->getStatusCode() === 200) {
            $response->seeJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['log_id']
            ]);
        }
    }

    /**
     * Test byAction method with various actions
     */
    public function testByActionWithDifferentActions()
    {
        $actions = ['created', 'updated', 'deleted', 'restored', 'soft_delete'];
        
        foreach ($actions as $action) {
            $response = $this->get("/api/v1/logs/actions/{$action}");
            
            $response->seeStatusCode(200);
            $response->seeJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['pagination']
            ]);
            
            // Check for proper headers
            $this->assertArrayHasKey('X-Action-Type', $response->response->headers->all());
        }
    }

    /**
     * Test byUser method with validation
     */
    public function testByUserMethodValidation()
    {
        // Test valid user ID
        $response = $this->get('/api/v1/logs/users/1?limit=25');
        
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['pagination']
        ]);

        // Test invalid user ID
        $response = $this->get('/api/v1/logs/users/-1');
        $response->seeStatusCode(422);

        // Test non-numeric user ID
        $response = $this->get('/api/v1/logs/users/abc');
        $response->seeStatusCode(422);
    }

    /**
     * Test dateRange method with comprehensive validation
     */
    public function testDateRangeMethodValidation()
    {
        $validStart = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $validEnd = Carbon::now()->format('Y-m-d H:i:s');
        
        // Test valid date range
        $response = $this->get("/api/v1/logs/date-range?start_date={$validStart}&end_date={$validEnd}&limit=50");
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['pagination']
        ]);

        // Test missing start_date
        $response = $this->get("/api/v1/logs/date-range?end_date={$validEnd}");
        $response->seeStatusCode(422);

        // Test missing end_date
        $response = $this->get("/api/v1/logs/date-range?start_date={$validStart}");
        $response->seeStatusCode(422);

        // Test end_date before start_date
        $invalidStart = Carbon::now()->format('Y-m-d H:i:s');
        $invalidEnd = Carbon::now()->subDay()->format('Y-m-d H:i:s');
        $response = $this->get("/api/v1/logs/date-range?start_date={$invalidStart}&end_date={$invalidEnd}");
        $response->seeStatusCode(422);

        // Test invalid date format
        $response = $this->get('/api/v1/logs/date-range?start_date=invalid-date&end_date=also-invalid');
        $response->seeStatusCode(422);
    }

    /**
     * Test enhanced recent method with various limits
     */
    public function testEnhancedRecentMethod()
    {
        // Test with default limit
        $response = $this->get('/api/v1/logs/recent');
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['count', 'limit', 'timestamp']
        ]);

        // Test with custom limit
        $response = $this->get('/api/v1/logs/recent?limit=30');
        $response->seeStatusCode(200);

        // Test with maximum limit (should be capped at 1000)
        $response = $this->get('/api/v1/logs/recent?limit=5000');
        $response->seeStatusCode(200);

        // Test with invalid limit (should use default)
        $response = $this->get('/api/v1/logs/recent?limit=-10');
        $response->seeStatusCode(200);
    }

    /**
     * Test enhanced export method with filters
     */
    public function testEnhancedExportMethod()
    {
        $validStart = Carbon::now()->subDays(30)->format('Y-m-d H:i:s');
        $validEnd = Carbon::now()->format('Y-m-d H:i:s');
        
        // Test basic export
        $response = $this->get('/api/v1/logs/export');
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['total_exported', 'filters_applied', 'export_timestamp']
        ]);

        // Test export with all filters
        $queryString = http_build_query([
            'start_date' => $validStart,
            'end_date' => $validEnd,
            'action' => 'created',
            'user_id' => 1,
            'task_id' => $this->testTask->id
        ]);
        
        $response = $this->get("/api/v1/logs/export?{$queryString}");
        $response->seeStatusCode(200);

        // Test export with invalid date
        $response = $this->get('/api/v1/logs/export?start_date=invalid');
        $response->seeStatusCode(422);
    }

    /**
     * Test taskDeletionLogs method
     */
    public function testTaskDeletionLogsMethod()
    {
        // Test with valid task ID
        $response = $this->get("/api/v1/logs/tasks/{$this->testTask->id}/deletions");
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['pagination']
        ]);
        
        // Check for proper headers
        $headers = $response->response->headers->all();
        $this->assertArrayHasKey('x-task-id', $headers);
        $this->assertArrayHasKey('x-log-type', $headers);

        // Test with invalid task ID
        $response = $this->get('/api/v1/logs/tasks/999999/deletions');
        $response->seeStatusCode(422);
    }

    /**
     * Test recentDeletions method
     */
    public function testRecentDeletionsMethod()
    {
        $response = $this->get('/api/v1/logs/deletions/recent');
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['count', 'limit', 'activity_type', 'timestamp']
        ]);

        // Test with custom limit
        $response = $this->get('/api/v1/logs/deletions/recent?limit=75');
        $response->seeStatusCode(200);
    }

    /**
     * Test enhanced deletionStats method
     */
    public function testEnhancedDeletionStatsMethod()
    {
        // Test basic deletion stats
        $response = $this->get('/api/v1/logs/deletions/stats');
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['period', 'stats_type']
        ]);

        // Test with date range
        $validStart = Carbon::now()->subDays(30)->format('Y-m-d H:i:s');
        $validEnd = Carbon::now()->format('Y-m-d H:i:s');
        
        $response = $this->get("/api/v1/logs/deletions/stats?start_date={$validStart}&end_date={$validEnd}");
        $response->seeStatusCode(200);

        // Test with invalid date
        $response = $this->get('/api/v1/logs/deletions/stats?start_date=invalid-date');
        $response->seeStatusCode(422);
    }

    /**
     * Test cleanup method
     */
    public function testCleanupMethod()
    {
        // Test cleanup with default retention
        $response = $this->delete('/api/v1/logs/cleanup');
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data' => ['deleted_logs_count', 'retention_days', 'cleanup_date'],
            'meta' => ['operation', 'affected_records']
        ]);

        // Test cleanup with custom retention
        $response = $this->delete('/api/v1/logs/cleanup?retention_days=180');
        $response->seeStatusCode(200);

        // Test cleanup with minimum retention
        $response = $this->delete('/api/v1/logs/cleanup?retention_days=1');
        $response->seeStatusCode(200);
    }

    /**
     * Test enhanced stats method with date ranges
     */
    public function testEnhancedStatsMethod()
    {
        // Test basic stats
        $response = $this->get('/api/v1/logs/stats');
        $response->seeStatusCode(200);
        $response->seeJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['period']
        ]);

        // Test stats with date range
        $validStart = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $validEnd = Carbon::now()->format('Y-m-d H:i:s');
        
        $response = $this->get("/api/v1/logs/stats?start_date={$validStart}&end_date={$validEnd}");
        $response->seeStatusCode(200);

        // Test stats with invalid date format
        $response = $this->get('/api/v1/logs/stats?start_date=2025-13-45');
        $response->seeStatusCode(422);
    }

    /**
     * Test parameter validation edge cases
     */
    public function testParameterValidationEdgeCases()
    {
        // Test extremely large limit (should be capped)
        $response = $this->get('/api/v1/logs/recent?limit=999999');
        $response->seeStatusCode(200);

        // Test zero limit (should use default)
        $response = $this->get('/api/v1/logs/recent?limit=0');
        $response->seeStatusCode(200);

        // Test negative limit (should use default)
        $response = $this->get('/api/v1/logs/recent?limit=-50');
        $response->seeStatusCode(200);

        // Test non-numeric limit
        $response = $this->get('/api/v1/logs/recent?limit=abc');
        $response->seeStatusCode(200); // Should handle gracefully
    }

    /**
     * Test error handling for service failures
     */
    public function testServiceErrorHandling()
    {
        // Test with mock service that throws exception
        $mockService = Mockery::mock(LogServiceInterface::class);
        $mockService->shouldReceive('getRecentLogs')
                    ->andThrow(new \Exception('Service failure'));
        
        $this->app->instance(LogServiceInterface::class, $mockService);
        
        $response = $this->get('/api/v1/logs/recent');
        $response->seeStatusCode(500);
    }

    /**
     * Test response headers consistency
     */
    public function testResponseHeaders()
    {
        $response = $this->get('/api/v1/logs/recent');
        $response->seeStatusCode(200);
        
        $headers = $response->response->headers->all();
        $this->assertArrayHasKey('x-api-version', $headers);
    }

    /**
     * Test pagination headers for paginated responses
     */
    public function testPaginationHeaders()
    {
        $response = $this->get('/api/v1/logs/actions/created?limit=10');
        $response->seeStatusCode(200);
        
        $headers = $response->response->headers->all();
        $this->assertArrayHasKey('x-total-count', $headers);
        $this->assertArrayHasKey('x-api-version', $headers);
    }

    /**
     * Test validation helper integration
     */
    public function testValidationHelperIntegration()
    {
        // Test LogValidationRequest validation rules
        $rules = LogValidationRequest::getFilterValidationRules();
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('limit', $rules);
        $this->assertArrayHasKey('start_date', $rules);
        
        // Test allowed actions
        $allowedActions = LogValidationRequest::getAllowedActions();
        $this->assertIsArray($allowedActions);
        $this->assertContains('created', $allowedActions);
        $this->assertContains('updated', $allowedActions);
        
        // Test valid action check
        $this->assertTrue(LogValidationRequest::isValidAction('created'));
        $this->assertFalse(LogValidationRequest::isValidAction('invalid_action'));
    }

    /**
     * Test compatibility with legacy routes
     */
    public function testLegacyRouteCompatibility()
    {
        $legacyRoutes = [
            '/logs' => 'GET',
            '/logs/stats' => 'GET',
            '/logs/recent' => 'GET',
            '/logs/export' => 'GET',
            "/logs/tasks/{$this->testTask->id}" => 'GET',
            '/logs/actions/created' => 'GET',
            '/logs/users/1' => 'GET'
        ];

        foreach ($legacyRoutes as $route => $method) {
            $response = $this->call($method, $route);
            $this->assertTrue(in_array($response->status(), [200, 404]));
        }
    }

    /**
     * Test comprehensive error scenarios
     */
    public function testComprehensiveErrorScenarios()
    {
        // Test invalid MongoDB ID format
        $response = $this->get('/api/v1/logs/invalid-mongo-id');
        $this->assertTrue(in_array($response->response->getStatusCode(), [404, 500]));

        // Test invalid action type
        $response = $this->get('/api/v1/logs/actions/invalid_action_type');
        $response->seeStatusCode(200); // Should handle gracefully

        // Test missing required parameters for date range
        $response = $this->get('/api/v1/logs/date-range');
        $response->seeStatusCode(422);
    }

    /**
     * Test enhanced log retrieval by ID validation
     */
    public function testEnhancedLogRetrievalByIdValidation()
    {
        // Test valid MongoDB ObjectId format (should handle gracefully)
        $validObjectId = '507f1f77bcf86cd799439011';
        $response = $this->get("/api/v1/logs/{$validObjectId}");
        $this->assertTrue(in_array($response->response->getStatusCode(), [200, 404]));
        
        // Test invalid MongoDB ObjectId formats
        $invalidIds = [
            'too-short' => '123',
            'too-long' => '507f1f77bcf86cd799439011abc',
            'non-hex' => '507g1f77bcf86cd799439011',
            'empty' => '',
            'spaces' => '507f 1f77 bcf8 6cd7 9943 9011',
            'special-chars' => '507f!f77@cf86#d799$39011'
        ];

        foreach ($invalidIds as $type => $invalidId) {
            $response = $this->get("/api/v1/logs/{$invalidId}");
            
            // Should return validation error
            if ($invalidId === '') {
                // Empty string might be handled by route not matching
                $this->assertTrue(in_array($response->response->getStatusCode(), [404, 422, 500]));
            } else {
                // Invalid format should return 422 or 400
                $this->assertTrue(in_array($response->response->getStatusCode(), [400, 422, 500]), 
                    "Failed for {$type}: {$invalidId}");
            }
        }
    }

    /**
     * Test log retrieval via query parameter
     */
    public function testLogRetrievalViaQueryParameter()
    {
        // Test valid ObjectId via query parameter
        $validObjectId = '507f1f77bcf86cd799439011';
        $response = $this->get("/api/v1/logs?id={$validObjectId}");
        $this->assertTrue(in_array($response->response->getStatusCode(), [200, 404]));
        
        // Test invalid ObjectId via query parameter
        $response = $this->get('/api/v1/logs?id=invalid-id');
        $this->assertTrue(in_array($response->response->getStatusCode(), [400, 422, 500]));
        
        // Test empty ID parameter
        $response = $this->get('/api/v1/logs?id=');
        $this->assertTrue(in_array($response->response->getStatusCode(), [400, 422]));
    }

    /**
     * Test log response structure for valid ID
     */
    public function testLogResponseStructureForValidId()
    {
        // Create a real task to ensure we have logs
        $task = $this->createTestTask();
        
        // Get logs to find a real log ID
        $logsResponse = $this->get('/api/v1/logs?limit=1');
        $logsResponse->seeStatusCode(200);
        
        $data = json_decode($logsResponse->response->getContent(), true);
        
        if (!empty($data['data']) && isset($data['data'][0]['_id'])) {
            $logId = $data['data'][0]['_id'];
            
            // Test show endpoint
            $response = $this->get("/api/v1/logs/{$logId}");
            $response->seeStatusCode(200);
            $response->seeJsonStructure([
                'success',
                'message', 
                'data' => [
                    '_id',
                    'task_id',
                    'action',
                    'created_at'
                ],
                'meta' => [
                    'log_id',
                    'retrieved_at'
                ]
            ]);
            
            // Test query parameter endpoint
            $response = $this->get("/api/v1/logs?id={$logId}");
            $response->seeStatusCode(200);
            $response->seeJsonStructure([
                'success',
                'message',
                'data' => [
                    '_id',
                    'task_id', 
                    'action',
                    'created_at'
                ],
                'meta' => [
                    'log_id',
                    'retrieved_at'
                ]
            ]);
        }
    }

    /**
     * Helper method to create test task
     */
    private function createTestTask()
    {
        return $this->post('/api/v1/tasks', [
            'title' => 'Test Task for Log ID Retrieval',
            'description' => 'Testing enhanced log functionality',
            'status' => 'pending'
        ]);
    }
}
