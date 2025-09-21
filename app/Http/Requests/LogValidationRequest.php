<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Services\ValidationMessageService;
use Carbon\Carbon;

class LogValidationRequest
{
    /**
     * Get validation rules for log filtering
     *
     * @return array
     */
    public static function getFilterValidationRules(): array
    {
        return [
            'limit' => ['integer', 'min:1', 'max:1000'],
            'page' => ['integer', 'min:1'],
            'sort_by' => ['string', Rule::in(['created_at', 'action', 'task_id', 'user_id'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'action' => ['string', 'max:100'],
            'task_id' => ['integer', 'min:1'],
            'user_id' => ['integer', 'min:1'],
            'start_date' => ['date', 'date_format:Y-m-d H:i:s'],
            'end_date' => ['date', 'date_format:Y-m-d H:i:s', 'after:start_date'],
            'level' => ['string', Rule::in(['info', 'warning', 'error', 'debug'])],
            'source' => ['string', 'max:100']
        ];
    }

    /**
     * Get custom validation messages for log filtering
     *
     * @return array
     */
    public static function getFilterValidationMessages(): array
    {
        return ValidationMessageService::getLogValidationMessages();
    }

    /**
     * Get validation rules for date range queries
     *
     * @return array
     */
    public static function getDateRangeValidationRules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'limit' => ['integer', 'min:1', 'max:1000']
        ];
    }

    /**
     * Get custom validation messages for date range queries
     *
     * @return array
     */
    public static function getDateRangeValidationMessages(): array
    {
        return ValidationMessageService::getDateRangeValidationMessages();
    }

    /**
     * Get validation rules for log export
     *
     * @return array
     */
    public static function getExportValidationRules(): array
    {
        return [
            'format' => ['string', Rule::in(['json', 'csv', 'xml'])],
            'start_date' => ['date'],
            'end_date' => ['date', 'after:start_date'],
            'action' => ['string', 'max:100'],
            'task_id' => ['integer', 'min:1'],
            'user_id' => ['integer', 'min:1'],
            'level' => ['string', Rule::in(['info', 'warning', 'error', 'debug'])],
            'max_records' => ['integer', 'min:1', 'max:10000']
        ];
    }

    /**
     * Get custom validation messages for log export
     *
     * @return array
     */
    public static function getExportValidationMessages(): array
    {
        return ValidationMessageService::getLogExportMessages();
    }

    /**
     * Get validation rules for cleanup operations
     *
     * @return array
     */
    public static function getCleanupValidationRules(): array
    {
        return [
            'retention_days' => ['integer', 'min:1', 'max:3650'], // Max 10 years
            'dry_run' => ['boolean'],
            'confirm' => ['boolean', 'required_if:dry_run,false']
        ];
    }

    /**
     * Get custom validation messages for cleanup operations
     *
     * @return array
     */
    public static function getCleanupValidationMessages(): array
    {
        return ValidationMessageService::getLogCleanupMessages();
    }

    /**
     * Validate action parameter
     *
     * @param string $action
     * @return bool
     */
    public static function isValidAction(string $action): bool
    {
        $allowedActions = [
            'created', 'updated', 'deleted', 'restored',
            'soft_delete', 'force_delete', 'bulk_update',
            'status_change', 'assignment_change', 'metadata_update'
        ];

        return in_array($action, $allowedActions, true);
    }

    /**
     * Get allowed action types
     *
     * @return array
     */
    public static function getAllowedActions(): array
    {
        return [
            'created', 'updated', 'deleted', 'restored',
            'soft_delete', 'force_delete', 'bulk_update',
            'status_change', 'assignment_change', 'metadata_update'
        ];
    }

    /**
     * Validate log level parameter
     *
     * @param string $level
     * @return bool
     */
    public static function isValidLevel(string $level): bool
    {
        $allowedLevels = ['info', 'warning', 'error', 'debug'];
        return in_array($level, $allowedLevels, true);
    }

    /**
     * Get allowed log levels
     *
     * @return array
     */
    public static function getAllowedLevels(): array
    {
        return ['info', 'warning', 'error', 'debug'];
    }

    /**
     * Validate and parse date parameter
     *
     * @param string $date
     * @return Carbon|null
     * @throws \Exception
     */
    public static function parseDate(string $date): ?Carbon
    {
        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            throw new \Exception("Invalid date format: {$date}. Expected format: Y-m-d H:i:s or ISO 8601");
        }
    }

    /**
     * Sanitize and validate limit parameter
     *
     * @param mixed $limit
     * @param int $default
     * @param int $max
     * @return int
     */
    public static function sanitizeLimit($limit, int $default = 50, int $max = 1000): int
    {
        $limit = (int) $limit;
        
        if ($limit <= 0) {
            return $default;
        }
        
        return min($limit, $max);
    }

    /**
     * Sanitize and validate page parameter
     *
     * @param mixed $page
     * @param int $default
     * @return int
     */
    public static function sanitizePage($page, int $default = 1): int
    {
        $page = (int) $page;
        return max($page, $default);
    }
}