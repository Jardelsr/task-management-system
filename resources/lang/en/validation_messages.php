<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task Management System - English Validation Messages
    |--------------------------------------------------------------------------
    */

    'task_creation' => [
        'title.required' => 'A task title is required and cannot be empty.',
        'title.string' => 'The task title must be a valid text string.',
        'title.min' => 'The task title must be at least 1 character long.',
        'title.max' => 'The task title cannot exceed 255 characters.',
        'title.regex' => 'The task title contains invalid characters. Only letters, numbers, spaces, and common punctuation (.,!?-_) are allowed.',
        
        'description.string' => 'The task description must be a valid text string.',
        'description.max' => 'The task description cannot exceed 1,000 characters.',
        
        'status.string' => 'The status must be a valid text value.',
        'status.in' => 'The selected status is invalid. Please choose from: :values',
        
        'created_by.integer' => 'The creator ID must be a valid number.',
        'created_by.min' => 'The creator ID must be a positive number greater than 0.',
        
        'assigned_to.integer' => 'The assignee ID must be a valid number.',
        'assigned_to.min' => 'The assignee ID must be a positive number greater than 0.',
        
        'due_date.date' => 'The due date must be a valid date.',
        'due_date.after' => 'The due date must be in the future.',
        'due_date.before' => 'The due date cannot be more than 10 years in the future.',
        
        'priority.string' => 'The priority must be a valid text value.',
        'priority.in' => 'The priority must be one of: low, medium, or high.',
    ],

    'business_rules' => [
        'status_transition.invalid' => 'Invalid status transition from ":from" to ":to".',
        'status_transition.completed_requires_completion_date' => 'Marking a task as completed requires setting a completion date.',
        'status_transition.cannot_reopen_completed' => 'Completed tasks cannot be reopened. Please create a new task instead.',
        
        'assignment.self_assignment' => 'You cannot assign a task to yourself as both creator and assignee.',
        'assignment.invalid_user' => 'The specified user ID does not exist or is inactive.',
        
        'due_date.overdue_completion' => 'Cannot mark an overdue task as completed without acknowledging the delay.',
        'due_date.past_due_update' => 'Cannot set a due date in the past for active tasks.',
        
        'task_deletion.has_dependencies' => 'This task cannot be deleted because other tasks depend on it.',
        'task_deletion.already_completed' => 'Completed tasks cannot be deleted, only archived.',
        
        'priority.escalation_required' => 'High priority tasks require manager approval for assignment changes.',
    ],

    'context' => [
        'task_not_found' => 'The requested task (ID: :id) could not be found or may have been deleted.',
        'user_not_found' => 'The specified user (ID: :id) does not exist in the system.',
        'validation_failed' => 'The submitted data contains errors. Please review and correct the highlighted fields.',
        'operation_failed' => 'The requested operation could not be completed due to system constraints.',
        'permission_denied' => 'You do not have sufficient permissions to perform this action.',
        'system_error' => 'A system error occurred. Please try again or contact support if the problem persists.',
    ],

];