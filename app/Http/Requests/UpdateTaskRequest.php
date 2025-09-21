<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Models\Task;
use App\Services\ValidationMessageService;
use Carbon\Carbon;

class UpdateTaskRequest extends FormRequest
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
            'title' => 'sometimes|required|string|min:3|max:255|regex:/^[\p{L}\p{N}\s\-_.,!?():;]+$/u|not_regex:/[\n\r\t]/',
            'description' => 'sometimes|nullable|string|max:1000',
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Task::getAvailableStatuses())
            ],
            'assigned_to' => 'sometimes|nullable|bail|integer|min:1|max:999999',
            'created_by' => 'sometimes|nullable|bail|integer|min:1|max:999999',
            'due_date' => [
                'sometimes',
                'nullable',
                'date',
                'after:now',
                'before:' . Carbon::now()->addYears(10)->toDateString()
            ],
            'priority' => 'sometimes|nullable|string|in:low,medium,high',
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
                
                // Special handling for priority
                if ($field === 'priority') {
                    $rules[$field] = [
                        'sometimes',
                        'nullable',
                        'string',
                        'in:low,medium,high'
                    ];
                }
                
                // Special handling for assigned_to with boolean check
                if ($field === 'assigned_to') {
                    $rules[$field] = 'sometimes|nullable|bail|integer|min:1|max:999999';
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
        return ValidationMessageService::getTaskUpdateMessages();
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