<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Task;
use App\Exceptions\TaskValidationException;
use Carbon\Carbon;

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
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Trim whitespace
                $value = trim($value);
                
                // Remove control characters except newlines and tabs
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                
                // Convert empty strings to null for nullable fields
                if ($value === '' && in_array($key, ['description', 'assigned_to', 'due_date', 'completed_at'])) {
                    $value = null;
                }
            }
            
            $sanitized[$key] = $value;
        }

        // Convert string 'true'/'false' to boolean for boolean fields
        $booleanFields = ['overdue', 'with_due_date'];
        foreach ($booleanFields as $field) {
            if (isset($sanitized[$field]) && is_string($sanitized[$field])) {
                $sanitized[$field] = filter_var($sanitized[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        // Convert numeric strings to integers for integer fields
        $integerFields = ['assigned_to', 'created_by', 'limit', 'page', 'task_id', 'user_id'];
        foreach ($integerFields as $field) {
            if (isset($sanitized[$field]) && is_string($sanitized[$field]) && is_numeric($sanitized[$field])) {
                $sanitized[$field] = (int) $sanitized[$field];
            }
        }

        return $sanitized;
    }

    /**
     * Filter data to only include non-empty values for partial updates
     *
     * @param array $data
     * @return array
     */
    public static function filterPartialUpdateData(array $data): array
    {
        $filtered = [];
        
        foreach ($data as $key => $value) {
            // Include the field if:
            // - It's not null and not empty string
            // - OR it's explicitly null (for clearing fields)
            // - OR it's a boolean false
            // - OR it's the number 0
            if ($value !== '' && ($value !== null || array_key_exists($key, $data))) {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }

    /**
     * Prepare data for partial update with automatic field management
     *
     * @param array $data
     * @return array
     */
    public static function preparePartialUpdateData(array $data): array
    {
        $prepared = self::filterPartialUpdateData($data);
        $prepared = self::sanitizeInput($prepared);

        // Handle status change logic first, before validation
        if (isset($prepared['status'])) {
            if ($prepared['status'] === Task::STATUS_COMPLETED && !isset($prepared['completed_at'])) {
                $prepared['completed_at'] = Carbon::now();
            } elseif ($prepared['status'] !== Task::STATUS_COMPLETED) {
                // Only clear completed_at if it's not explicitly being set to something
                if (!array_key_exists('completed_at', $prepared)) {
                    $prepared['completed_at'] = null;
                }
            }
        }

        return $prepared;
    }

    /**
     * Get only the fields that are allowed for partial updates
     *
     * @param array $data
     * @return array
     */
    public static function getAllowedUpdateFields(array $data): array
    {
        $allowedFields = [
            'title',
            'description', 
            'status',
            'assigned_to',
            'due_date',
            'completed_at'
        ];

        return array_intersect_key($data, array_flip($allowedFields));
    }

    /**
     * Validate update data with comprehensive business logic validation
     *
     * @param array $data Update data
     * @param mixed|null $existingTask Existing task for context validation (Task model or object)
     * @return array Array of validation errors (empty if valid)
     * @throws TaskValidationException
     */
    public static function validateUpdateData(array $data, $existingTask = null): array
    {
        $errors = [];

        // Validate individual fields
        $fieldErrors = self::validateUpdateFields($data);
        if (!empty($fieldErrors)) {
            $errors = array_merge($errors, $fieldErrors);
        }

        // Validate business logic if we have existing task context
        if ($existingTask) {
            $businessErrors = self::validateBusinessLogic($data, $existingTask);
            if (!empty($businessErrors)) {
                $errors = array_merge($errors, $businessErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate individual fields with custom business rules
     *
     * @param array $data
     * @return array
     */
    public static function validateUpdateFields(array $data): array
    {
        $errors = [];

        // Validate title if provided
        if (isset($data['title'])) {
            if (empty(trim($data['title']))) {
                $errors['title'][] = 'Title cannot be empty';
            } elseif (strlen($data['title']) > 255) {
                $errors['title'][] = 'Title cannot exceed 255 characters';
            } elseif (strlen($data['title']) < 3) {
                $errors['title'][] = 'Title must be at least 3 characters';
            } elseif (!preg_match('/^[\p{L}\p{N}\s\-_.,!?()]+$/u', $data['title'])) {
                $errors['title'][] = 'Title contains invalid characters';
            }
        }

        // Validate description if provided
        if (isset($data['description']) && $data['description'] !== null) {
            if (strlen($data['description']) > 1000) {
                $errors['description'][] = 'Description cannot exceed 1000 characters';
            }
        }

        // Validate status if provided
        if (isset($data['status'])) {
            if (!in_array($data['status'], Task::getAvailableStatuses())) {
                $errors['status'][] = 'Invalid status. Valid options: ' . implode(', ', Task::getAvailableStatuses());
            }
        }

        // Validate user IDs if provided
        foreach (['assigned_to', 'created_by'] as $userField) {
            if (isset($data[$userField]) && $data[$userField] !== null) {
                if (!is_numeric($data[$userField]) || intval($data[$userField]) < 1) {
                    $errors[$userField][] = 'Must be a positive integer';
                } elseif (intval($data[$userField]) > 999999) {
                    $errors[$userField][] = 'User ID too large (max: 999999)';
                }
            }
        }

        // Validate dates if provided
        if (isset($data['due_date']) && $data['due_date'] !== null) {
            try {
                $dueDate = Carbon::parse($data['due_date']);
                if ($dueDate->isPast()) {
                    $errors['due_date'][] = 'Due date must be in the future';
                } elseif ($dueDate->isAfter(Carbon::now()->addYears(10))) {
                    $errors['due_date'][] = 'Due date cannot be more than 10 years in the future';
                }
            } catch (\Exception $e) {
                $errors['due_date'][] = 'Invalid date format';
            }
        }

        if (isset($data['completed_at']) && $data['completed_at'] !== null) {
            try {
                $completedAt = Carbon::parse($data['completed_at']);
                if ($completedAt->isFuture()) {
                    $errors['completed_at'][] = 'Completion date cannot be in the future';
                }
            } catch (\Exception $e) {
                $errors['completed_at'][] = 'Invalid date format';
            }
        }

        return $errors;
    }

    /**
     * Validate business logic rules
     *
     * @param array $data Update data
     * @param mixed $existingTask Existing task (Task model or object with properties)
     * @return array
     */
    public static function validateBusinessLogic(array $data, $existingTask): array
    {
        $errors = [];

        // Validate status transitions
        if (isset($data['status']) && $data['status'] !== $existingTask->status) {
            $transitionErrors = self::validateStatusTransition($existingTask->status, $data['status']);
            if (!empty($transitionErrors)) {
                $errors = array_merge($errors, $transitionErrors);
            }
        }

        // Validate completion logic
        if (isset($data['status']) || isset($data['completed_at'])) {
            $completionErrors = self::validateCompletionLogic($data, $existingTask);
            if (!empty($completionErrors)) {
                $errors = array_merge($errors, $completionErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate status transitions
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return array
     */
    public static function validateStatusTransition(string $currentStatus, string $newStatus): array
    {
        $validTransitions = [
            Task::STATUS_PENDING => [Task::STATUS_IN_PROGRESS, Task::STATUS_COMPLETED, Task::STATUS_CANCELLED],
            Task::STATUS_IN_PROGRESS => [Task::STATUS_PENDING, Task::STATUS_COMPLETED, Task::STATUS_CANCELLED],
            Task::STATUS_COMPLETED => [Task::STATUS_IN_PROGRESS], // Allow reopening
            Task::STATUS_CANCELLED => [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS] // Allow reactivation
        ];

        if (!isset($validTransitions[$currentStatus])) {
            return ['status' => ['Invalid current status']];
        }

        if (!in_array($newStatus, $validTransitions[$currentStatus])) {
            return ['status' => [
                "Cannot transition from '{$currentStatus}' to '{$newStatus}'. " .
                "Valid transitions: " . implode(', ', $validTransitions[$currentStatus])
            ]];
        }

        return [];
    }

    /**
     * Validate completion logic consistency
     *
     * @param array $data
     * @param mixed $existingTask Task model or object with properties
     * @return array
     */
    public static function validateCompletionLogic(array $data, $existingTask): array
    {
        $errors = [];
        $newStatus = $data['status'] ?? $existingTask->status;
        $newCompletedAt = array_key_exists('completed_at', $data) ? $data['completed_at'] : $existingTask->completed_at;

        // If status is being set to completed, ensure completed_at is set
        if ($newStatus === Task::STATUS_COMPLETED && $newCompletedAt === null) {
            $errors['completed_at'][] = 'Completion date is required when status is completed';
        }

        // If status is not completed, completed_at should be null
        if ($newStatus !== Task::STATUS_COMPLETED && $newCompletedAt !== null) {
            $errors['completed_at'][] = 'Completion date should only be set when status is completed';
        }

        return $errors;
    }

    /**
     * Perform comprehensive validation for task update
     *
     * @param array $data
     * @param mixed|null $existingTask Task model or object
     * @return array Validated and processed data
     * @throws TaskValidationException
     */
    public static function validateAndPrepareUpdateData(array $data, $existingTask = null): array
    {
        // Get only allowed fields
        $allowedData = self::getAllowedUpdateFields($data);
        
        // Sanitize input
        $sanitizedData = self::sanitizeInput($allowedData);
        
        // Filter for partial update
        $partialData = self::filterPartialUpdateData($sanitizedData);

        // Skip further validation if no data to update
        if (empty($partialData)) {
            return [];
        }

        // Prepare final data with automatic field management BEFORE validation
        $preparedData = self::preparePartialUpdateData($partialData);

        // Validate the prepared data
        $errors = self::validateUpdateData($preparedData, $existingTask);
        
        if (!empty($errors)) {
            throw new TaskValidationException($errors, null, 'Update validation failed');
        }

        return $preparedData;
    }
}