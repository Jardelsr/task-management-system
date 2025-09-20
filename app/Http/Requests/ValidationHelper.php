<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Task;
use App\Exceptions\TaskValidationException;

class ValidationHelper
{
    /**
     * Validate task filtering parameters
     *
     * @param Request $request
     * @return array
     * @throws TaskValidationException
     */
    public static function validateFilterParameters(Request $request): array
    {
        $validator = app('validator')->make($request->all(), [
            'status' => ['sometimes', 'nullable', Rule::in(Task::getAvailableStatuses())],
            'assigned_to' => 'sometimes|nullable|integer|min:1',
            'created_by' => 'sometimes|nullable|integer|min:1',
            'overdue' => 'sometimes|boolean',
            'with_due_date' => 'sometimes|boolean',
            'sort_by' => 'sometimes|in:created_at,updated_at,due_date,title,status',
            'sort_order' => 'sometimes|in:asc,desc',
            'limit' => 'sometimes|integer|min:1|max:1000',
            'page' => 'sometimes|integer|min:1'
        ]);

        if ($validator->fails()) {
            throw new TaskValidationException(
                $validator->errors()->toArray(),
                null,
                'Filter validation failed'
            );
        }

        return $validator->validated();
    }

    /**
     * Validate task ID parameter
     *
     * @param mixed $id
     * @return int
     * @throws TaskValidationException
     */
    public static function validateTaskId($id): int
    {
        if (!is_numeric($id) || intval($id) <= 0) {
            throw new TaskValidationException(
                ['id' => ['The task ID must be a positive integer']],
                'id',
                'Invalid task ID'
            );
        }

        return intval($id);
    }

    /**
     * Validate log query parameters
     *
     * @param Request $request
     * @return array
     * @throws TaskValidationException
     */
    public static function validateLogParameters(Request $request): array
    {
        $validator = app('validator')->make($request->all(), [
            'id' => 'sometimes|string|regex:/^[0-9a-fA-F]{24}$/', // MongoDB ObjectId format
            'limit' => 'sometimes|integer|min:1|max:100',
            'task_id' => 'sometimes|integer|min:1',
            'action' => 'sometimes|string|in:created,updated,deleted',
            'user_id' => 'sometimes|integer|min:1'
        ]);

        if ($validator->fails()) {
            throw new TaskValidationException(
                $validator->errors()->toArray(),
                null,
                'Log parameters validation failed'
            );
        }

        return $validator->validated();
    }

    /**
     * Get validation error messages
     *
     * @return array
     */
    public static function getCommonErrorMessages(): array
    {
        return [
            'integer' => 'The :attribute must be a valid integer.',
            'min' => 'The :attribute must be at least :min.',
            'max' => 'The :attribute may not be greater than :max.',
            'boolean' => 'The :attribute field must be true or false.',
            'in' => 'The selected :attribute is invalid.',
            'regex' => 'The :attribute format is invalid.',
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute must be a string.',
            'date' => 'The :attribute must be a valid date.',
            'after' => 'The :attribute must be a date after :date.'
        ];
    }

    /**
     * Sanitize input data
     *
     * @param array $data
     * @return array
     */
    public static function sanitizeInput(array $data): array
    {
        // Trim string values
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }

        // Convert string 'true'/'false' to boolean for boolean fields
        $booleanFields = ['overdue', 'with_due_date'];
        foreach ($booleanFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        // Convert numeric strings to integers for integer fields
        $integerFields = ['assigned_to', 'created_by', 'limit', 'page', 'task_id', 'user_id'];
        foreach ($integerFields as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && is_numeric($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        return $data;
    }
}