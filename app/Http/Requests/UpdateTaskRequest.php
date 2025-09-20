<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Models\Task;
use Carbon\Carbon;

class UpdateTaskRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return self::getValidationRules();
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return self::getValidationMessages();
    }

    /**
     * Get validation rules as a static method for use in controllers
     *
     * @return array
     */
    public static function getValidationRules(): array
    {
        return [
            'title' => 'sometimes|required|string|min:3|max:255|regex:/^[\p{L}\p{N}\s\-_.,!?()]+$/u',
            'description' => 'sometimes|nullable|string|max:1000',
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Task::getAvailableStatuses())
            ],
            'assigned_to' => 'sometimes|nullable|integer|min:1|max:999999',
            'created_by' => 'sometimes|nullable|integer|min:1|max:999999',
            'due_date' => [
                'sometimes',
                'nullable',
                'date',
                'after:now',
                'before:' . Carbon::now()->addYears(10)->toDateString()
            ],
            'completed_at' => 'sometimes|nullable|date|before_or_equal:now'
        ];
    }

    /**
     * Get validation rules for partial updates (only validate provided fields)
     *
     * @param array $data The data being validated
     * @return array
     */
    public static function getPartialUpdateRules(array $data): array
    {
        $rules = [];
        $allRules = self::getValidationRules();

        // Only include rules for fields that are actually provided
        foreach ($data as $field => $value) {
            if (array_key_exists($field, $allRules)) {
                $rules[$field] = $allRules[$field];
                
                // Special handling for status transitions
                if ($field === 'status') {
                    $rules[$field] = [
                        'required',
                        'string',
                        Rule::in(Task::getAvailableStatuses())
                    ];
                }
                
                // Special handling for due_date when updating existing task
                if ($field === 'due_date' && $value !== null) {
                    $rules[$field] = [
                        'nullable',
                        'date',
                        'after:now',
                        'before:' . Carbon::now()->addYears(10)->toDateString()
                    ];
                }
                
                // Special handling for completed_at
                if ($field === 'completed_at' && $value !== null) {
                    $rules[$field] = [
                        'nullable',
                        'date',
                        'before_or_equal:now'
                    ];
                }
            }
        }

        return $rules;
    }

    /**
     * Get validation messages as a static method for use in controllers
     *
     * @return array
     */
    public static function getValidationMessages(): array
    {
        return [
            'title.required' => 'The task title is required.',
            'title.string' => 'The task title must be a valid string.',
            'title.min' => 'The task title must be at least 3 characters.',
            'title.max' => 'The task title may not be greater than 255 characters.',
            'title.regex' => 'The task title contains invalid characters. Only letters, numbers, spaces, and common punctuation are allowed.',
            
            'description.string' => 'The description must be a valid string.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            
            'status.required' => 'The status field is required when updating status.',
            'status.string' => 'The status must be a valid string.',
            'status.in' => 'The selected status is invalid. Valid options are: ' . implode(', ', Task::getAvailableStatuses()),
            
            'assigned_to.integer' => 'The assigned to field must be a valid integer.',
            'assigned_to.min' => 'The assigned to field must be at least 1.',
            'assigned_to.max' => 'The assigned to field must not exceed 999999.',
            
            'created_by.integer' => 'The created by field must be a valid integer.',
            'created_by.min' => 'The created by field must be at least 1.',
            'created_by.max' => 'The created by field must not exceed 999999.',
            
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after' => 'The due date must be a date after now.',
            'due_date.before' => 'The due date must be within the next 10 years.',
            
            'completed_at.date' => 'The completed at field must be a valid date.',
            'completed_at.before_or_equal' => 'The completed at date cannot be in the future.'
        ];
    }

    /**
     * Get status transition validation rules
     *
     * @param string $currentStatus Current task status
     * @param string $newStatus New status being set
     * @return array
     */
    public static function getStatusTransitionRules(string $currentStatus, string $newStatus): array
    {
        $validTransitions = [
            Task::STATUS_PENDING => [Task::STATUS_IN_PROGRESS, Task::STATUS_COMPLETED, Task::STATUS_CANCELLED],
            Task::STATUS_IN_PROGRESS => [Task::STATUS_PENDING, Task::STATUS_COMPLETED, Task::STATUS_CANCELLED],
            Task::STATUS_COMPLETED => [Task::STATUS_IN_PROGRESS], // Can reopen completed tasks
            Task::STATUS_CANCELLED => [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS] // Can reactivate cancelled tasks
        ];

        if (!isset($validTransitions[$currentStatus])) {
            return ['status' => 'Invalid current status'];
        }

        if (!in_array($newStatus, $validTransitions[$currentStatus])) {
            return [
                'status' => "Cannot transition from '{$currentStatus}' to '{$newStatus}'. " .
                           "Valid transitions are: " . implode(', ', $validTransitions[$currentStatus])
            ];
        }

        return [];
    }

    /**
     * Validate status consistency with completed_at field
     *
     * @param array $data
     * @return array
     */
    public static function validateStatusConsistency(array $data): array
    {
        $errors = [];

        // If status is completed, completed_at should be set
        if (isset($data['status']) && $data['status'] === Task::STATUS_COMPLETED) {
            if (isset($data['completed_at']) && $data['completed_at'] === null) {
                $errors['completed_at'] = ['Completed at date is required when status is completed'];
            }
        }

        // If status is not completed, completed_at should be null
        if (isset($data['status']) && $data['status'] !== Task::STATUS_COMPLETED) {
            if (isset($data['completed_at']) && $data['completed_at'] !== null) {
                $errors['completed_at'] = ['Completed at date should only be set when status is completed'];
            }
        }

        return $errors;
    }
}