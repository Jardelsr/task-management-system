<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
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
        return [
            'limit.integer' => 'The limit must be an integer.',
            'limit.min' => 'The limit must be at least 1.',
            'limit.max' => 'The limit must not exceed 1000.',
            'page.integer' => 'The page must be an integer.',
            'page.min' => 'The page must be at least 1.',
            'sort_by.in' => 'The sort_by field must be one of: created_at, action, task_id, user_id.',
            'sort_order.in' => 'The sort_order must be either asc or desc.',
            'action.string' => 'The action must be a string.',
            'action.max' => 'The action may not be greater than 100 characters.',
            'task_id.integer' => 'The task_id must be an integer.',
            'task_id.min' => 'The task_id must be at least 1.',
            'user_id.integer' => 'The user_id must be an integer.',
            'user_id.min' => 'The user_id must be at least 1.',
            'start_date.date' => 'The start_date must be a valid date.',
            'start_date.date_format' => 'The start_date must be in format: Y-m-d H:i:s.',
            'end_date.date' => 'The end_date must be a valid date.',
            'end_date.date_format' => 'The end_date must be in format: Y-m-d H:i:s.',
            'end_date.after' => 'The end_date must be after the start_date.',
            'level.in' => 'The level must be one of: info, warning, error, debug.',
            'source.string' => 'The source must be a string.',
            'source.max' => 'The source may not be greater than 100 characters.'
        ];
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
        return [
            'start_date.required' => 'The start_date field is required.',
            'start_date.date' => 'The start_date must be a valid date.',
            'end_date.required' => 'The end_date field is required.',
            'end_date.date' => 'The end_date must be a valid date.',
            'end_date.after' => 'The end_date must be after the start_date.',
            'limit.integer' => 'The limit must be an integer.',
            'limit.min' => 'The limit must be at least 1.',
            'limit.max' => 'The limit must not exceed 1000.'
        ];
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
        return [
            'format.in' => 'The format must be one of: json, csv, xml.',
            'start_date.date' => 'The start_date must be a valid date.',
            'end_date.date' => 'The end_date must be a valid date.',
            'end_date.after' => 'The end_date must be after the start_date.',
            'action.string' => 'The action must be a string.',
            'action.max' => 'The action may not be greater than 100 characters.',
            'task_id.integer' => 'The task_id must be an integer.',
            'task_id.min' => 'The task_id must be at least 1.',
            'user_id.integer' => 'The user_id must be an integer.',
            'user_id.min' => 'The user_id must be at least 1.',
            'level.in' => 'The level must be one of: info, warning, error, debug.',
            'max_records.integer' => 'The max_records must be an integer.',
            'max_records.min' => 'The max_records must be at least 1.',
            'max_records.max' => 'The max_records must not exceed 10,000.'
        ];
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
        return [
            'retention_days.integer' => 'The retention_days must be an integer.',
            'retention_days.min' => 'The retention_days must be at least 1.',
            'retention_days.max' => 'The retention_days must not exceed 3,650 (10 years).',
            'dry_run.boolean' => 'The dry_run must be true or false.',
            'confirm.boolean' => 'The confirm must be true or false.',
            'confirm.required_if' => 'The confirm field is required when not performing a dry run.'
        ];
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