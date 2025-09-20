<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Models\Task;

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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => [
                'sometimes',
                Rule::in(Task::getAvailableStatuses())
            ],
            'assigned_to' => 'nullable|integer|min:1',
            'due_date' => 'nullable|date|after:now',
            'completed_at' => 'nullable|date'
        ];
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
            'title.max' => 'The task title may not be greater than 255 characters.',
            'description.string' => 'The description must be a valid string.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            'status.in' => 'The selected status is invalid. Valid options are: ' . implode(', ', Task::getAvailableStatuses()),
            'assigned_to.integer' => 'The assigned to field must be a valid integer.',
            'assigned_to.min' => 'The assigned to field must be at least 1.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after' => 'The due date must be a date after today.',
            'completed_at.date' => 'The completed at field must be a valid date.'
        ];
    }
}