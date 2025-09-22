<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\LogService;
use App\Services\LogResponseFormatter;
use App\Repositories\LogRepositoryInterface;
use App\Models\TaskLog;
use App\Exceptions\LoggingException;
use Mockery;
use Carbon\Carbon;

class LogServiceTest extends TestCase
{
    protected $logRepository;
    protected $responseFormatter;
    protected $logService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logRepository = Mockery::mock(LogRepositoryInterface::class);
        $this->responseFormatter = Mockery::mock(LogResponseFormatter::class);
        $this->logService = new LogService($this->logRepository, $this->responseFormatter);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCreateLogEntry()
    {
        $taskId = 1;
        $action = 'created';
        $details = ['title' => 'Test Task'];

        $expectedLog = new TaskLog([
            'task_id' => $taskId,
            'action' => $action,
            'details' => $details,
            'timestamp' => now()
        ]);

        $this->logRepository->shouldReceive('create')
            ->once()
            ->with([
                'task_id' => $taskId,
                'action' => $action,
                'details' => $details
            ])
            ->andReturn($expectedLog);

        $result = $this->logService->createLog($taskId, $action, $details);

        $this->assertInstanceOf(TaskLog::class, $result);
        $this->assertEquals($taskId, $result->task_id);
        $this->assertEquals($action, $result->action);
    }

    public function testCreateLogEntryThrowsExceptionOnFailure()
    {
        $this->expectException(LoggingException::class);

        $this->logRepository->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $this->logService->createLog(1, 'test', []);
    }

    public function testGetLogsByTaskId()
    {
        $taskId = 1;
        $expectedLogs = collect([
            new TaskLog(['task_id' => $taskId, 'action' => 'created']),
            new TaskLog(['task_id' => $taskId, 'action' => 'updated'])
        ]);

        $this->logRepository->shouldReceive('getByTaskId')
            ->once()
            ->with($taskId)
            ->andReturn($expectedLogs);

        $result = $this->logService->getLogsByTaskId($taskId);

        $this->assertCount(2, $result);
        $this->assertEquals($expectedLogs, $result);
    }

    public function testGetAllLogsWithPagination()
    {
        $page = 1;
        $perPage = 10;
        $filters = ['action' => 'created'];

        $expectedLogs = collect([
            new TaskLog(['action' => 'created']),
            new TaskLog(['action' => 'created'])
        ]);

        $this->logRepository->shouldReceive('getAllWithFilters')
            ->once()
            ->with($filters, $page, $perPage)
            ->andReturn($expectedLogs);

        $this->responseFormatter->shouldReceive('formatResponse')
            ->once()
            ->with($expectedLogs, $page, $perPage, 2)
            ->andReturn([
                'data' => $expectedLogs->toArray(),
                'pagination' => ['current_page' => $page]
            ]);

        $result = $this->logService->getAllLogs($filters, $page, $perPage);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function testDeleteLogsByTaskId()
    {
        $taskId = 1;

        $this->logRepository->shouldReceive('deleteByTaskId')
            ->once()
            ->with($taskId)
            ->andReturn(true);

        $result = $this->logService->deleteLogsByTaskId($taskId);

        $this->assertTrue($result);
    }

    public function testValidateLogData()
    {
        $validData = [
            'task_id' => 1,
            'action' => 'created',
            'details' => ['title' => 'Test']
        ];

        // Test valid data - should not throw exception
        $reflection = new \ReflectionClass($this->logService);
        $method = $reflection->getMethod('validateLogData');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($this->logService, $validData));
    }

    public function testValidateLogDataThrowsExceptionForInvalidData()
    {
        $this->expectException(LoggingException::class);

        $invalidData = [
            'task_id' => null, // Invalid task_id
            'action' => 'created',
            'details' => ['title' => 'Test']
        ];

        $reflection = new \ReflectionClass($this->logService);
        $method = $reflection->getMethod('validateLogData');
        $method->setAccessible(true);

        $method->invoke($this->logService, $invalidData);
    }
}