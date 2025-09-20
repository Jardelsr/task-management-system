<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Task;

class UpdateTaskRequest extends FormRequest
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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'status' => [
                'sometimes',
                'required',
                Rule::in(Task::getAvailableStatuses())
            ],
            'created_by' => 'sometimes|nullable|integer|min:1',
            'assigned_to' => 'sometimes|nullable|integer|min:1',
            'due_date' => 'sometimes|nullable|date|after:now',
            'completed_at' => 'sometimes|nullable|date'
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
            'status.required' => 'The task status is required.',
            'status.in' => 'The selected status is invalid. Valid statuses are: ' . implode(', ', Task::getAvailableStatuses()),
            'created_by.integer' => 'The created_by field must be a valid user ID.',
            'created_by.min' => 'The created_by field must be a positive integer.',
            'assigned_to.integer' => 'The assigned_to field must be a valid user ID.',
            'assigned_to.min' => 'The assigned_to field must be a positive integer.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after' => 'The due date must be in the future.',
            'completed_at.date' => 'The completed at field must be a valid date.'
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
            'due_date' => 'due date',
            'completed_at' => 'completion date'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Handle status-specific logic
        if ($this->has('status') && $this->status === Task::STATUS_COMPLETED) {
            // Auto-set completed_at when status is changed to completed
            if (!$this->has('completed_at') || !$this->completed_at) {
                $this->merge([
                    'completed_at' => now()->toDateTimeString()
                ]);
            }
        }

        // Convert dates to proper format if provided
        foreach (['due_date', 'completed_at'] as $dateField) {
            if ($this->has($dateField) && $this->$dateField) {
                try {
                    $this->merge([
                        $dateField => \Carbon\Carbon::parse($this->$dateField)->toDateTimeString()
                    ]);
                } catch (\Exception $e) {
                    // Let validation handle the error
                }
            }
        }

        // Clear completed_at if status is not completed
        if ($this->has('status') && $this->status !== Task::STATUS_COMPLETED) {
            $this->merge([
                'completed_at' => null
            ]);
        }
    }
}