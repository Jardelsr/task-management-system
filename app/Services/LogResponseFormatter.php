<?php

namespace App\Services;

use App\Models\TaskLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

/**
 * Service for formatting log responses consistently across the API
 */
class LogResponseFormatter
{
    /**
     * Default fields to include in log responses
     */
    const DEFAULT_FIELDS = [
        '_id',
        'task_id',
        'action',
        'old_data',
        'new_data',
        'user_id',
        'user_name',
        'description',
        'created_at',
        'updated_at'
    ];

    /**
     * Fields that should always be included regardless of field filtering
     */
    const REQUIRED_FIELDS = ['_id', 'task_id', 'action', 'created_at'];

    /**
     * Configuration array
     *
     * @var array
     */
    protected array $config;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = config('log_responses', []);
    }

    /**
     * Get default fields from configuration
     *
     * @return array
     */
    protected function getDefaultFields(): array
    {
        return $this->config['default_fields'] ?? self::DEFAULT_FIELDS;
    }

    /**
     * Get required fields from configuration
     *
     * @return array
     */
    protected function getRequiredFields(): array
    {
        return $this->config['required_fields'] ?? self::REQUIRED_FIELDS;
    }

    /**
     * Get default date format from configuration
     *
     * @return string
     */
    protected function getDefaultDateFormat(): string
    {
        return $this->config['date_format']['default'] ?? 'iso8601';
    }

    /**
     * Format a single TaskLog for API response
     *
     * @param TaskLog $log
     * @param array $options
     * @return array
     */
    public function formatSingleLog(TaskLog $log, array $options = []): array
    {
        $includeFields = $options['fields'] ?? $this->getDefaultFields();
        $includeMetadata = $options['include_metadata'] ?? ($this->config['metadata']['include_by_default'] ?? true);
        $dateFormat = $options['date_format'] ?? $this->getDefaultDateFormat();

        $formatted = $this->extractLogFields($log, $includeFields, $dateFormat);

        if ($includeMetadata) {
            $formatted = $this->addSingleLogMetadata($formatted, $log, $options);
        }

        return $formatted;
    }

    /**
     * Format a collection of TaskLogs for API response
     *
     * @param Collection $logs
     * @param array $options
     * @return array
     */
    public function formatLogCollection(Collection $logs, array $options = []): array
    {
        $includeFields = $options['fields'] ?? $this->getDefaultFields();
        $includeMetadata = $options['include_metadata'] ?? ($this->config['metadata']['include_by_default'] ?? true);
        $dateFormat = $options['date_format'] ?? $this->getDefaultDateFormat();

        $formattedLogs = $logs->map(function (TaskLog $log) use ($includeFields, $dateFormat, $options) {
            $formatted = $this->extractLogFields($log, $includeFields, $dateFormat);
            
            if ($options['include_individual_metadata'] ?? ($this->config['metadata']['include_individual_metadata'] ?? false)) {
                $formatted = $this->addSingleLogMetadata($formatted, $log, $options);
            }
            
            return $formatted;
        })->toArray();

        if ($includeMetadata) {
            return $this->addCollectionMetadata($formattedLogs, $logs, $options);
        }

        return $formattedLogs;
    }

    /**
     * Format logs with pagination information
     *
     * @param Collection $logs
     * @param array $pagination
     * @param array $options
     * @return array
     */
    public function formatPaginatedLogs(Collection $logs, array $pagination, array $options = []): array
    {
        $formattedLogs = $this->formatLogCollection($logs, array_merge($options, [
            'include_metadata' => false // We'll add metadata at the top level
        ]));

        $response = [
            'logs' => $formattedLogs,
            'pagination' => $this->formatPaginationMetadata($pagination),
            'meta' => $this->buildResponseMetadata($logs, $options)
        ];

        return $response;
    }

    /**
     * Format log statistics for API response
     *
     * @param array $statistics
     * @param array $options
     * @return array
     */
    public function formatLogStatistics(array $statistics, array $options = []): array
    {
        $formatted = [
            'total_logs' => $statistics['total_logs'] ?? 0,
            'logs_by_action' => $this->formatActionStatistics($statistics['logs_by_action'] ?? []),
            'recent_activity' => $this->formatRecentActivity($statistics['recent_activity'] ?? []),
            'date_range' => $this->formatDateRange($statistics['date_range'] ?? [])
        ];

        if (isset($statistics['performance'])) {
            $formatted['performance'] = $this->formatPerformanceMetrics($statistics['performance']);
        }

        $formatted['meta'] = [
            'generated_at' => Carbon::now()->toISOString(),
            'period_analyzed' => $options['period'] ?? 'all_time',
            'data_freshness' => 'real_time'
        ];

        return $formatted;
    }

    /**
     * Extract and format specific fields from a TaskLog
     *
     * @param TaskLog $log
     * @param array $includeFields
     * @param string $dateFormat
     * @return array
     */
    protected function extractLogFields(TaskLog $log, array $includeFields, string $dateFormat): array
    {
        $fields = array_unique(array_merge($this->getRequiredFields(), $includeFields));
        $formatted = [];

        foreach ($fields as $field) {
            switch ($field) {
                case '_id':
                    $formatted['id'] = (string) $log->_id;
                    break;
                    
                case 'task_id':
                    $formatted['task_id'] = (int) $log->task_id;
                    break;
                    
                case 'action':
                    $formatted['action'] = $log->action;
                    $formatted['action_display'] = $this->getActionDisplayName($log->action);
                    break;
                    
                case 'old_data':
                    $formatted['old_data'] = $this->formatLogData($log->old_data);
                    break;
                    
                case 'new_data':
                    $formatted['new_data'] = $this->formatLogData($log->new_data);
                    break;
                    
                case 'user_id':
                    $formatted['user_id'] = $log->user_id ? (int) $log->user_id : null;
                    break;
                    
                case 'user_name':
                    $formatted['user_name'] = $log->user_name ?? ($this->config['custom_processors']['user_name']['default_value'] ?? 'System');
                    break;
                    
                case 'description':
                    $formatted['description'] = $log->description ?? $this->generateDefaultDescription($log);
                    break;
                    
                case 'created_at':
                    $formatted['created_at'] = $this->formatDate($log->created_at, $dateFormat);
                    break;
                    
                case 'updated_at':
                    $formatted['updated_at'] = $this->formatDate($log->updated_at, $dateFormat);
                    break;
                    
                default:
                    if (isset($log->$field)) {
                        $formatted[$field] = $this->applySecurityMasking($field, $log->$field);
                    }
                    break;
            }
        }

        return $formatted;
    }

    /**
     * Add metadata to a single log entry
     *
     * @param array $formatted
     * @param TaskLog $log
     * @param array $options
     * @return array
     */
    protected function addSingleLogMetadata(array $formatted, TaskLog $log, array $options): array
    {
        $formatted['meta'] = [
            'log_age' => $this->calculateLogAge($log->created_at),
            'has_changes' => $this->hasDataChanges($log),
            'change_summary' => $this->generateChangeSummary($log),
            'formatted_message' => $this->generateFormattedMessage($log)
        ];

        if ($options['include_technical_meta'] ?? false) {
            $formatted['meta']['technical'] = [
                'collection' => 'task_logs',
                'mongo_id' => (string) $log->_id,
                'document_size' => $this->estimateDocumentSize($log)
            ];
        }

        return $formatted;
    }

    /**
     * Add metadata to a log collection
     *
     * @param array $formattedLogs
     * @param Collection $originalLogs
     * @param array $options
     * @return array
     */
    protected function addCollectionMetadata(array $formattedLogs, Collection $originalLogs, array $options): array
    {
        return [
            'logs' => $formattedLogs,
            'meta' => $this->buildResponseMetadata($originalLogs, $options)
        ];
    }

    /**
     * Build comprehensive response metadata
     *
     * @param Collection $logs
     * @param array $options
     * @return array
     */
    protected function buildResponseMetadata(Collection $logs, array $options): array
    {
        $meta = [
            'total_returned' => $logs->count(),
            'data_type' => 'log_collection',
            'response_time' => microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : microtime(true)),
            'timestamp' => Carbon::now()->toISOString()
        ];

        // Add action distribution
        if ($logs->isNotEmpty()) {
            $actionStats = $logs->groupBy('action')->map->count();
            $meta['action_distribution'] = $actionStats->toArray();
            
            $dateRange = [
                'oldest' => $logs->min('created_at'),
                'newest' => $logs->max('created_at')
            ];
            $meta['date_range'] = [
                'oldest' => $this->formatDate($dateRange['oldest']),
                'newest' => $this->formatDate($dateRange['newest'])
            ];
        }

        // Add filtering information if available
        if (isset($options['applied_filters'])) {
            $meta['applied_filters'] = array_filter($options['applied_filters'], function($value) {
                return $value !== null && $value !== '';
            });
        }

        return $meta;
    }

    /**
     * Format pagination metadata
     *
     * @param array $pagination
     * @return array
     */
    protected function formatPaginationMetadata(array $pagination): array
    {
        return [
            'current_page' => (int) ($pagination['current_page'] ?? 1),
            'per_page' => (int) ($pagination['per_page'] ?? 50),
            'total' => (int) ($pagination['total'] ?? 0),
            'last_page' => (int) ($pagination['last_page'] ?? 1),
            'from' => (int) ($pagination['from'] ?? 0),
            'to' => (int) ($pagination['to'] ?? 0),
            'has_next_page' => $pagination['has_next_page'] ?? false,
            'has_previous_page' => $pagination['has_previous_page'] ?? false,
            'links' => $this->generatePaginationLinks($pagination)
        ];
    }

    /**
     * Format log data (old_data/new_data) for response
     *
     * @param mixed $data
     * @return array|null
     */
    protected function formatLogData($data): ?array
    {
        if (empty($data)) {
            return null;
        }

        if (is_array($data)) {
            return $data;
        }

        if (is_object($data)) {
            return json_decode(json_encode($data), true);
        }

        return ['raw' => $data];
    }

    /**
     * Format date according to specified format
     *
     * @param mixed $date
     * @param string $format
     * @return string|null
     */
    protected function formatDate($date, string $format = 'iso8601'): ?string
    {
        if (!$date) {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        switch ($format) {
            case 'iso8601':
                return $carbon->toISOString();
            case 'timestamp':
                return $carbon->timestamp;
            case 'human':
                return $carbon->diffForHumans();
            case 'date_only':
                return $carbon->toDateString();
            case 'datetime':
                return $carbon->toDateTimeString();
            default:
                return $carbon->format($format);
        }
    }

    /**
     * Get display name for action
     *
     * @param string $action
     * @return string
     */
    protected function getActionDisplayName(string $action): string
    {
        $displayNames = $this->config['action_display_names'] ?? [
            TaskLog::ACTION_CREATED => 'Created',
            TaskLog::ACTION_UPDATED => 'Updated', 
            TaskLog::ACTION_DELETED => 'Deleted',
            TaskLog::ACTION_RESTORED => 'Restored',
            TaskLog::ACTION_FORCE_DELETED => 'Permanently Deleted'
        ];

        return $displayNames[$action] ?? ucfirst($action);
    }

    /**
     * Apply security masking to sensitive fields
     *
     * @param string $fieldName
     * @param mixed $value
     * @return mixed
     */
    protected function applySecurityMasking(string $fieldName, $value)
    {
        if (!($this->config['security']['mask_sensitive_fields'] ?? true)) {
            return $value;
        }

        $sensitivePatterns = $this->config['security']['sensitive_field_patterns'] ?? [
            'password', 'token', 'secret', 'key', 'credential'
        ];

        $fieldLower = strtolower($fieldName);
        foreach ($sensitivePatterns as $pattern) {
            if (strpos($fieldLower, $pattern) !== false) {
                $maskChar = $this->config['security']['mask_character'] ?? '*';
                $maskLength = $this->config['security']['mask_length'] ?? 8;
                return str_repeat($maskChar, $maskLength);
            }
        }

        return $value;
    }

    /**
     * Generate a default description for a log entry
     *
     * @param TaskLog $log
     * @return string
     */
    protected function generateDefaultDescription(TaskLog $log): string
    {
        $userName = $log->user_name ?? 'System';
        $actionDisplay = $this->getActionDisplayName($log->action);
        
        return "{$userName} {$actionDisplay} task #{$log->task_id}";
    }

    /**
     * Calculate log age in human-readable format
     *
     * @param mixed $createdAt
     * @return string
     */
    protected function calculateLogAge($createdAt): string
    {
        $carbon = $createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt);
        return $carbon->diffForHumans();
    }

    /**
     * Check if log has data changes
     *
     * @param TaskLog $log
     * @return bool
     */
    protected function hasDataChanges(TaskLog $log): bool
    {
        return !empty($log->old_data) && !empty($log->new_data);
    }

    /**
     * Generate change summary for update logs
     *
     * @param TaskLog $log
     * @return array|null
     */
    protected function generateChangeSummary(TaskLog $log): ?array
    {
        if ($log->action !== TaskLog::ACTION_UPDATED || !$this->hasDataChanges($log)) {
            return null;
        }

        $oldData = is_array($log->old_data) ? $log->old_data : [];
        $newData = is_array($log->new_data) ? $log->new_data : [];
        
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
        
        foreach ($allKeys as $key) {
            $oldValue = $oldData[$key] ?? null;
            $newValue = $newData[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $key,
                    'from' => $oldValue,
                    'to' => $newValue,
                    'type' => $this->getChangeType($oldValue, $newValue)
                ];
            }
        }

        return empty($changes) ? null : $changes;
    }

    /**
     * Generate formatted message for log entry
     *
     * @param TaskLog $log
     * @return string
     */
    protected function generateFormattedMessage(TaskLog $log): string
    {
        $userName = $log->user_name ?? 'System';
        $actionDisplay = $this->getActionDisplayName($log->action);
        
        $message = "{$userName} {$actionDisplay} task #{$log->task_id}";
        
        if ($log->action === TaskLog::ACTION_UPDATED && $this->hasDataChanges($log)) {
            $changeSummary = $this->generateChangeSummary($log);
            if ($changeSummary) {
                $fieldCount = count($changeSummary);
                $message .= " ({$fieldCount} field" . ($fieldCount > 1 ? 's' : '') . " changed)";
            }
        }
        
        return $message;
    }

    /**
     * Get the type of change between two values
     *
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return string
     */
    protected function getChangeType($oldValue, $newValue): string
    {
        if ($oldValue === null && $newValue !== null) {
            return 'added';
        }
        
        if ($oldValue !== null && $newValue === null) {
            return 'removed';
        }
        
        return 'modified';
    }

    /**
     * Estimate document size for technical metadata
     *
     * @param TaskLog $log
     * @return string
     */
    protected function estimateDocumentSize(TaskLog $log): string
    {
        $size = strlen(json_encode($log->toArray()));
        
        if ($size > 1024) {
            return round($size / 1024, 2) . ' KB';
        }
        
        return $size . ' bytes';
    }

    /**
     * Format action statistics
     *
     * @param array $actionStats
     * @return array
     */
    protected function formatActionStatistics(array $actionStats): array
    {
        $formatted = [];
        
        foreach ($actionStats as $action => $count) {
            $formatted[] = [
                'action' => $action,
                'action_display' => $this->getActionDisplayName($action),
                'count' => (int) $count,
                'percentage' => 0 // Will be calculated if total is available
            ];
        }
        
        // Calculate percentages
        $total = array_sum($actionStats);
        if ($total > 0) {
            foreach ($formatted as &$stat) {
                $stat['percentage'] = round(($stat['count'] / $total) * 100, 2);
            }
        }
        
        return $formatted;
    }

    /**
     * Format recent activity data
     *
     * @param array $recentActivity
     * @return array
     */
    protected function formatRecentActivity(array $recentActivity): array
    {
        $formatted = [
            'period' => '7 days',
            'total_activity' => array_sum($recentActivity),
            'by_action' => []
        ];
        
        foreach ($recentActivity as $action => $count) {
            $formatted['by_action'][] = [
                'action' => $action,
                'action_display' => $this->getActionDisplayName($action),
                'count' => (int) $count
            ];
        }
        
        return $formatted;
    }

    /**
     * Format date range information
     *
     * @param array $dateRange
     * @return array
     */
    protected function formatDateRange(array $dateRange): array
    {
        return [
            'from' => $dateRange['from'] ?? null,
            'to' => $dateRange['to'] ?? null,
            'span' => $this->calculateDateSpan($dateRange)
        ];
    }

    /**
     * Calculate the span between two dates
     *
     * @param array $dateRange
     * @return string|null
     */
    protected function calculateDateSpan(array $dateRange): ?string
    {
        if (empty($dateRange['from']) || empty($dateRange['to'])) {
            return null;
        }
        
        try {
            $from = Carbon::parse($dateRange['from']);
            $to = Carbon::parse($dateRange['to']);
            return $from->diffForHumans($to, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format performance metrics
     *
     * @param array $performance
     * @return array
     */
    protected function formatPerformanceMetrics(array $performance): array
    {
        return [
            'query_time' => isset($performance['query_time']) ? round($performance['query_time'], 4) . 'ms' : null,
            'total_documents_scanned' => $performance['documents_scanned'] ?? null,
            'index_usage' => $performance['index_usage'] ?? 'unknown',
            'memory_usage' => isset($performance['memory_usage']) ? $this->formatMemoryUsage($performance['memory_usage']) : null
        ];
    }

    /**
     * Format memory usage for display
     *
     * @param int $bytes
     * @return string
     */
    protected function formatMemoryUsage(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Generate pagination links
     *
     * @param array $pagination
     * @return array
     */
    protected function generatePaginationLinks(array $pagination): array
    {
        $baseUrl = request()->url();
        $query = request()->query();
        
        $links = [
            'first' => $this->buildPaginationUrl($baseUrl, $query, 1),
            'last' => $this->buildPaginationUrl($baseUrl, $query, $pagination['last_page'] ?? 1),
            'prev' => null,
            'next' => null
        ];
        
        $currentPage = $pagination['current_page'] ?? 1;
        $lastPage = $pagination['last_page'] ?? 1;
        
        if ($currentPage > 1) {
            $links['prev'] = $this->buildPaginationUrl($baseUrl, $query, $currentPage - 1);
        }
        
        if ($currentPage < $lastPage) {
            $links['next'] = $this->buildPaginationUrl($baseUrl, $query, $currentPage + 1);
        }
        
        return $links;
    }

    /**
     * Build pagination URL
     *
     * @param string $baseUrl
     * @param array $query
     * @param int $page
     * @return string
     */
    protected function buildPaginationUrl(string $baseUrl, array $query, int $page): string
    {
        $query['page'] = $page;
        return $baseUrl . '?' . http_build_query($query);
    }
}