<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Task;

class CreateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => [
                'sometimes',
                Rule::in(Task::getAvailableStatuses())
            ],
            'created_by' => 'nullable|integer|min:1',
            'assigned_to' => 'nullable|integer|min:1',
            'due_date' => 'nullable|date|after:now'
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'The task title is required.',
            'title.max' => 'The task title cannot exceed 255 characters.',
            'description.max' => 'The task description cannot exceed 1000 characters.',
            'status.in' => 'The selected status is invalid. Valid statuses are: ' . implode(', ', Task::getAvailableStatuses()),
            'created_by.integer' => 'The created_by field must be a valid user ID.',
            'created_by.min' => 'The created_by field must be a positive integer.',
            'assigned_to.integer' => 'The assigned_to field must be a valid user ID.',
            'assigned_to.min' => 'The assigned_to field must be a positive integer.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after' => 'The due date must be in the future.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'created_by' => 'creator',
            'assigned_to' => 'assignee',
            'due_date' => 'due date'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge([
                'status' => Task::STATUS_PENDING
            ]);
        }

        // Convert due_date to proper format if provided
        if ($this->has('due_date') && $this->due_date) {
            try {
                $this->merge([
                    'due_date' => \Carbon\Carbon::parse($this->due_date)->toDateTimeString()
                ]);
            } catch (\Exception $e) {
                // Let validation handle the error
            }
        }
    }
}