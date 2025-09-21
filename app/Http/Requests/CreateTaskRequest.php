<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Models\Task;
use App\Services\ValidationMessageService;

class CreateTaskRequest extends FormRequest
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
            'title' => 'required|string|max:255|min:1',
            'description' => 'nullable|string|max:1000',
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(Task::getAvailableStatuses())
            ],
            'created_by' => 'nullable|integer|min:1',
            'assigned_to' => 'nullable|integer|min:1',
            'due_date' => 'nullable|date|after:now',
            'priority' => 'sometimes|nullable|string|in:low,medium,high'
        ];
    }

    /**
     * Get validation messages as a static method for use in controllers
     *
     * @return array
     */
    public static function getValidationMessages(): array
    {
        return ValidationMessageService::getTaskCreationMessages();
    }
}