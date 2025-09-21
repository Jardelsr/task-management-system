<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Models\Task;
use App\Services\ValidationMessageService;

class CreateTaskRequest extends FormRequest
{
    /**
     * Get field type mapping for sanitization
     *
     * @return array
     */
    public function typeMap(): array
    {
        return [
            'title' => 'string',
            'description' => 'string',
            'status' => 'string',
            'created_by' => 'integer',
            'assigned_to' => 'integer',
            'due_date' => 'string',
            'priority' => 'string'
        ];
    }

    /**
     * Get sanitization options for specific fields
     *
     * @return array
     */
    public function sanitizationOptions(): array
    {
        return [
            'title' => [
                'max_length' => 255,
                'skip_html_encode' => false,
                'allow_newlines' => false
            ],
            'description' => [
                'max_length' => 1000,
                'skip_html_encode' => false,
                'allow_newlines' => true
            ],
            'created_by' => [
                'min' => 1,
                'max' => 999999
            ],
            'assigned_to' => [
                'min' => 1,
                'max' => 999999
            ]
        ];
    }
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
            'title' => 'required|string|max:255|min:3|regex:/^[\p{L}\p{N}\s\-_.,!?():;]+$/u|not_regex:/[\n\r\t]/',
            'description' => 'nullable|string|max:1000',
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(Task::getAvailableStatuses())
            ],
            'created_by' => 'nullable|bail|integer|min:1',
            'assigned_to' => 'nullable|bail|integer|min:1',
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