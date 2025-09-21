<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task Management System - Custom Validation Messages
    |--------------------------------------------------------------------------
    |
    | This file contains all custom validation messages for the Task Management
    | System. Messages are organized by category for better maintainability
    | and consistency across the application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Task Creation Validation Messages
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

    /*
    |--------------------------------------------------------------------------
    | Task Update Validation Messages
    |--------------------------------------------------------------------------
    */
    'task_update' => [
        'title.required' => 'When updating the title, it cannot be empty.',
        'title.string' => 'The task title must be a valid text string.',
        'title.min' => 'The task title must be at least 3 characters long.',
        'title.max' => 'The task title cannot exceed 255 characters.',
        'title.regex' => 'The task title contains invalid characters. Only letters, numbers, spaces, and common punctuation are allowed.',
        
        'description.string' => 'The task description must be a valid text string.',
        'description.max' => 'The task description cannot exceed 1,000 characters.',
        
        'status.required' => 'A status is required when updating the task status.',
        'status.string' => 'The status must be a valid text value.',
        'status.in' => 'Invalid status provided. Valid options are: :values',
        
        'assigned_to.integer' => 'The assignee ID must be a valid number.',
        'assigned_to.min' => 'The assignee ID must be a positive number greater than 0.',
        'assigned_to.max' => 'The assignee ID cannot exceed 999,999.',
        
        'created_by.integer' => 'The creator ID must be a valid number.',
        'created_by.min' => 'The creator ID must be a positive number greater than 0.',
        'created_by.max' => 'The creator ID cannot exceed 999,999.',
        
        'due_date.date' => 'The due date must be a valid date.',
        'due_date.after' => 'The due date must be in the future.',
        'due_date.before' => 'The due date cannot be more than 10 years in the future.',
        
        'completed_at.date' => 'The completion date must be a valid date.',
        'completed_at.before_or_equal' => 'The completion date cannot be in the future.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Validation Messages
    |--------------------------------------------------------------------------
    */
    'log_validation' => [
        'limit.integer' => 'The limit must be a valid number.',
        'limit.min' => 'The limit must be at least 1 record.',
        'limit.max' => 'The limit cannot exceed 1,000 records per request.',
        
        'page.integer' => 'The page number must be a valid integer.',
        'page.min' => 'The page number must be at least 1.',
        
        'sort_by.in' => 'Invalid sort field. Allowed values are: created_at, action, task_id, user_id.',
        'sort_order.in' => 'Sort order must be either "asc" for ascending or "desc" for descending.',
        
        'action.string' => 'The action field must be a valid text string.',
        'action.max' => 'The action description cannot exceed 100 characters.',
        
        'task_id.integer' => 'The task ID must be a valid number.',
        'task_id.min' => 'The task ID must be a positive number greater than 0.',
        
        'user_id.integer' => 'The user ID must be a valid number.',
        'user_id.min' => 'The user ID must be a positive number greater than 0.',
        
        'start_date.date' => 'The start date must be a valid date.',
        'start_date.date_format' => 'The start date must be in format: YYYY-MM-DD HH:MM:SS.',
        
        'end_date.date' => 'The end date must be a valid date.',
        'end_date.date_format' => 'The end date must be in format: YYYY-MM-DD HH:MM:SS.',
        'end_date.after' => 'The end date must be after the start date.',
        
        'level.in' => 'Invalid log level. Allowed values are: info, warning, error, debug.',
        
        'source.string' => 'The source field must be a valid text string.',
        'source.max' => 'The source description cannot exceed 100 characters.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Export Validation Messages
    |--------------------------------------------------------------------------
    */
    'log_export' => [
        'format.in' => 'Invalid export format. Supported formats are: json, csv, xml.',
        'max_records.integer' => 'The maximum records limit must be a valid number.',
        'max_records.min' => 'The maximum records limit must be at least 1.',
        'max_records.max' => 'The maximum records limit cannot exceed 10,000.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Cleanup Validation Messages
    |--------------------------------------------------------------------------
    */
    'log_cleanup' => [
        'retention_days.integer' => 'The retention period must be a valid number of days.',
        'retention_days.min' => 'The retention period must be at least 1 day.',
        'retention_days.max' => 'The retention period cannot exceed 3,650 days (10 years).',
        
        'dry_run.boolean' => 'The dry run option must be true or false.',
        
        'confirm.boolean' => 'The confirmation must be true or false.',
        'confirm.required_if' => 'You must confirm the cleanup operation when dry run is disabled.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Logic Validation Messages
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Filter and Query Validation Messages
    |--------------------------------------------------------------------------
    */
    'filtering' => [
        'status.in' => 'Invalid status filter. Valid options are: :values',
        'assigned_to.integer' => 'The assigned user filter must be a valid user ID number.',
        'assigned_to.min' => 'The assigned user ID must be a positive number.',
        
        'created_by.integer' => 'The creator filter must be a valid user ID number.',
        'created_by.min' => 'The creator ID must be a positive number.',
        
        'overdue.boolean' => 'The overdue filter must be true or false.',
        'with_due_date.boolean' => 'The due date filter must be true or false.',
        
        'sort_by.in' => 'Invalid sort field. Allowed options are: created_at, updated_at, due_date, title, status.',
        'sort_order.in' => 'Sort order must be "asc" (ascending) or "desc" (descending).',
        
        'page.integer' => 'The page number must be a valid integer.',
        'page.min' => 'The page number must be at least 1.',
        
        'limit.integer' => 'The limit must be a valid number.',
        'limit.min' => 'The limit must be at least 1.',
        'limit.max' => 'The limit cannot exceed 100 results per page.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Common Field Validation Messages
    |--------------------------------------------------------------------------
    */
    'common' => [
        'required' => 'The :attribute field is required.',
        'string' => 'The :attribute must be valid text.',
        'integer' => 'The :attribute must be a valid number.',
        'boolean' => 'The :attribute must be true or false.',
        'date' => 'The :attribute must be a valid date.',
        'email' => 'The :attribute must be a valid email address.',
        'url' => 'The :attribute must be a valid URL.',
        'uuid' => 'The :attribute must be a valid UUID.',
        'json' => 'The :attribute must be valid JSON.',
        
        'min.string' => 'The :attribute must be at least :min characters.',
        'max.string' => 'The :attribute cannot exceed :max characters.',
        'min.numeric' => 'The :attribute must be at least :min.',
        'max.numeric' => 'The :attribute cannot exceed :max.',
        
        'unique' => 'This :attribute has already been taken.',
        'exists' => 'The selected :attribute is invalid.',
        'in' => 'The selected :attribute is invalid.',
        
        'regex' => 'The :attribute format is invalid.',
        'alpha' => 'The :attribute may only contain letters.',
        'alpha_num' => 'The :attribute may only contain letters and numbers.',
        'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Context Messages
    |--------------------------------------------------------------------------
    */
    'context' => [
        'task_not_found' => 'The requested task (ID: :id) could not be found or may have been deleted.',
        'user_not_found' => 'The specified user (ID: :id) does not exist in the system.',
        'validation_failed' => 'The submitted data contains errors. Please review and correct the highlighted fields.',
        'operation_failed' => 'The requested operation could not be completed due to system constraints.',
        'permission_denied' => 'You do not have sufficient permissions to perform this action.',
        'system_error' => 'A system error occurred. Please try again or contact support if the problem persists.',
    ],

];