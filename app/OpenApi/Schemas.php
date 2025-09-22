<?php

namespace App\OpenApi;

/**
 * OpenAPI Schema Definitions for Task Management System
 * 
 * This file contains all the schema definitions for the API documentation.
 * Each schema represents either a model, request, or response structure.
 */

/**
 * @OA\Schema(
 *     schema="Task",
 *     type="object",
 *     required={"id", "title", "status", "created_at", "updated_at"},
 *     @OA\Property(property="id", type="integer", example=1, description="Unique task identifier"),
 *     @OA\Property(property="title", type="string", maxLength=255, example="Complete project documentation", description="Task title"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Write comprehensive documentation for the project", description="Task description"),
 *     @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "cancelled"}, example="pending", description="Current task status"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, example="medium", description="Task priority level"),
 *     @OA\Property(property="created_by", type="integer", nullable=true, example=1, description="ID of user who created the task"),
 *     @OA\Property(property="assigned_to", type="integer", nullable=true, example=2, description="ID of user assigned to the task"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2024-12-31T23:59:59Z", description="Task due date"),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example="2024-12-15T10:30:00Z", description="Task completion date"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-12-01T09:00:00Z", description="Task creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-12-15T14:30:00Z", description="Task last update date"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null, description="Task soft deletion date (null if not deleted)")
 * )
 */
class TaskSchema {}

/**
 * @OA\Schema(
 *     schema="TaskCreateRequest",
 *     type="object",
 *     required={"title"},
 *     @OA\Property(property="title", type="string", maxLength=255, example="Complete project documentation", description="Task title (required)"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Write comprehensive documentation for the project", description="Task description (optional)"),
 *     @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "cancelled"}, default="pending", example="pending", description="Task status (optional, defaults to 'pending')"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, default="medium", example="medium", description="Task priority (optional, defaults to 'medium')"),
 *     @OA\Property(property="assigned_to", type="integer", nullable=true, example=2, description="ID of user to assign the task (optional)"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2024-12-31T23:59:59Z", description="Task due date (optional)")
 * )
 */
class TaskCreateRequestSchema {}

/**
 * @OA\Schema(
 *     schema="TaskUpdateRequest",
 *     type="object",
 *     @OA\Property(property="title", type="string", maxLength=255, example="Updated task title", description="Task title (optional for updates)"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Updated task description", description="Task description (optional for updates)"),
 *     @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "cancelled"}, example="in_progress", description="Task status (optional for updates)"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, example="high", description="Task priority (optional for updates)"),
 *     @OA\Property(property="assigned_to", type="integer", nullable=true, example=3, description="ID of user to assign the task (optional for updates)"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2024-12-31T23:59:59Z", description="Task due date (optional for updates)")
 * )
 */
class TaskUpdateRequestSchema {}

/**
 * @OA\Schema(
 *     schema="TaskBulkCreateRequest",
 *     type="object",
 *     required={"tasks"},
 *     @OA\Property(
 *         property="tasks",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/TaskCreateRequest"),
 *         example={
 *             {"title": "Task 1", "description": "First task", "priority": "high"},
 *             {"title": "Task 2", "description": "Second task", "priority": "medium"}
 *         },
 *         description="Array of task creation requests"
 *     )
 * )
 */
class TaskBulkCreateRequestSchema {}

/**
 * @OA\Schema(
 *     schema="TaskBulkUpdateRequest",
 *     type="object",
 *     required={"updates"},
 *     @OA\Property(
 *         property="updates",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             required={"id"},
 *             @OA\Property(property="id", type="integer", example=1, description="Task ID to update"),
 *             @OA\Property(property="title", type="string", example="Updated title", description="Updated task title"),
 *             @OA\Property(property="status", type="string", enum={"pending", "in_progress", "completed", "cancelled"}, example="completed", description="Updated task status"),
 *             @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, example="high", description="Updated task priority")
 *         ),
 *         description="Array of task update objects with ID and fields to update"
 *     )
 * )
 */
class TaskBulkUpdateRequestSchema {}

/**
 * @OA\Schema(
 *     schema="TaskBulkDeleteRequest",
 *     type="object",
 *     required={"task_ids"},
 *     @OA\Property(
 *         property="task_ids",
 *         type="array",
 *         @OA\Items(type="integer"),
 *         example={1, 2, 3, 4, 5},
 *         description="Array of task IDs to delete"
 *     ),
 *     @OA\Property(property="permanent", type="boolean", default=false, example=false, description="Whether to permanently delete tasks (true) or soft delete (false)")
 * )
 */
class TaskBulkDeleteRequestSchema {}

/**
 * @OA\Schema(
 *     schema="TaskAssignRequest",
 *     type="object",
 *     required={"assigned_to"},
 *     @OA\Property(property="assigned_to", type="integer", example=2, description="ID of user to assign the task to")
 * )
 */
class TaskAssignRequestSchema {}

/**
 * @OA\Schema(
 *     schema="TaskLog",
 *     type="object",
 *     required={"id", "task_id", "action", "timestamp"},
 *     @OA\Property(property="id", type="string", example="507f1f77bcf86cd799439011", description="Log entry ID (MongoDB ObjectId)"),
 *     @OA\Property(property="task_id", type="integer", example=1, description="Task ID this log relates to"),
 *     @OA\Property(property="action", type="string", example="created", description="Action performed on the task"),
 *     @OA\Property(property="user_id", type="integer", nullable=true, example=1, description="ID of user who performed the action"),
 *     @OA\Property(property="old_data", type="object", nullable=true, description="Previous task data before the action"),
 *     @OA\Property(property="new_data", type="object", nullable=true, description="New task data after the action"),
 *     @OA\Property(property="ip_address", type="string", example="192.168.1.1", description="IP address of the user"),
 *     @OA\Property(property="user_agent", type="string", example="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36", description="User agent string"),
 *     @OA\Property(property="timestamp", type="string", format="date-time", example="2024-12-01T09:00:00Z", description="When the action was performed")
 * )
 */
class TaskLogSchema {}

/**
 * @OA\Schema(
 *     schema="TaskStats",
 *     type="object",
 *     @OA\Property(property="total", type="integer", example=150, description="Total number of tasks"),
 *     @OA\Property(property="pending", type="integer", example=45, description="Number of pending tasks"),
 *     @OA\Property(property="in_progress", type="integer", example=30, description="Number of in-progress tasks"),
 *     @OA\Property(property="completed", type="integer", example=70, description="Number of completed tasks"),
 *     @OA\Property(property="cancelled", type="integer", example=5, description="Number of cancelled tasks"),
 *     @OA\Property(property="overdue", type="integer", example=8, description="Number of overdue tasks"),
 *     @OA\Property(property="due_today", type="integer", example=3, description="Number of tasks due today"),
 *     @OA\Property(property="due_this_week", type="integer", example=12, description="Number of tasks due this week"),
 *     @OA\Property(
 *         property="priority_distribution",
 *         type="object",
 *         @OA\Property(property="low", type="integer", example=40, description="Number of low priority tasks"),
 *         @OA\Property(property="medium", type="integer", example=70, description="Number of medium priority tasks"),
 *         @OA\Property(property="high", type="integer", example=30, description="Number of high priority tasks"),
 *         @OA\Property(property="urgent", type="integer", example=10, description="Number of urgent priority tasks")
 *     )
 * )
 */
class TaskStatsSchema {}

/**
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     required={"success", "timestamp", "message"},
 *     @OA\Property(property="success", type="boolean", example=true, description="Whether the operation was successful"),
 *     @OA\Property(property="timestamp", type="string", format="date-time", example="2024-12-01T09:00:00Z", description="Response timestamp"),
 *     @OA\Property(property="message", type="string", example="Operation completed successfully", description="Success message"),
 *     @OA\Property(property="data", type="object", description="Response data (varies by endpoint)"),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="request_id", type="string", example="req_507f1f77bcf86cd799439011", description="Unique request identifier"),
 *         @OA\Property(property="api_version", type="string", example="1.0", description="API version"),
 *         @OA\Property(property="execution_time", type="string", example="150.25ms", description="Request execution time")
 *     )
 * )
 */
class SuccessResponseSchema {}

/**
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     type="object",
 *     required={"success", "timestamp", "message", "data", "meta"},
 *     @OA\Property(property="success", type="boolean", example=true, description="Whether the operation was successful"),
 *     @OA\Property(property="timestamp", type="string", format="date-time", example="2024-12-01T09:00:00Z", description="Response timestamp"),
 *     @OA\Property(property="message", type="string", example="Data retrieved successfully", description="Success message"),
 *     @OA\Property(property="data", type="array", @OA\Items(type="object"), description="Array of response data items"),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="request_id", type="string", example="req_507f1f77bcf86cd799439011", description="Unique request identifier"),
 *         @OA\Property(property="api_version", type="string", example="1.0", description="API version"),
 *         @OA\Property(property="execution_time", type="string", example="150.25ms", description="Request execution time"),
 *         @OA\Property(property="data_count", type="integer", example=25, description="Number of items in current page"),
 *         @OA\Property(
 *             property="pagination",
 *             type="object",
 *             @OA\Property(property="current_page", type="integer", example=1, description="Current page number"),
 *             @OA\Property(property="per_page", type="integer", example=25, description="Items per page"),
 *             @OA\Property(property="total", type="integer", example=100, description="Total number of items"),
 *             @OA\Property(property="total_pages", type="integer", example=4, description="Total number of pages"),
 *             @OA\Property(property="has_next_page", type="boolean", example=true, description="Whether there is a next page"),
 *             @OA\Property(property="has_previous_page", type="boolean", example=false, description="Whether there is a previous page"),
 *             @OA\Property(property="next_page", type="integer", nullable=true, example=2, description="Next page number"),
 *             @OA\Property(property="previous_page", type="integer", nullable=true, example=null, description="Previous page number")
 *         )
 *     )
 * )
 */
class PaginatedResponseSchema {}

/**
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     required={"success", "timestamp", "error"},
 *     @OA\Property(property="success", type="boolean", example=false, description="Always false for error responses"),
 *     @OA\Property(property="timestamp", type="string", format="date-time", example="2024-12-01T09:00:00Z", description="Error timestamp"),
 *     @OA\Property(property="error", type="string", example="Task not found", description="Error message"),
 *     @OA\Property(property="error_code", type="string", example="TASK_NOT_FOUND", description="Machine-readable error code"),
 *     @OA\Property(property="details", type="object", description="Additional error details"),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="request_id", type="string", example="req_507f1f77bcf86cd799439011", description="Unique request identifier"),
 *         @OA\Property(property="api_version", type="string", example="1.0", description="API version")
 *     )
 * )
 */
class ErrorSchema {}

/**
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     required={"success", "timestamp", "error", "validation_errors"},
 *     @OA\Property(property="success", type="boolean", example=false, description="Always false for error responses"),
 *     @OA\Property(property="timestamp", type="string", format="date-time", example="2024-12-01T09:00:00Z", description="Error timestamp"),
 *     @OA\Property(property="error", type="string", example="Validation failed", description="Main error message"),
 *     @OA\Property(property="error_code", type="string", example="VALIDATION_FAILED", description="Machine-readable error code"),
 *     @OA\Property(
 *         property="validation_errors",
 *         type="object",
 *         example={
 *             "title": {"The title field is required."},
 *             "status": {"The selected status is invalid."}
 *         },
 *         description="Field-specific validation errors"
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="request_id", type="string", example="req_507f1f77bcf86cd799439011", description="Unique request identifier"),
 *         @OA\Property(property="api_version", type="string", example="1.0", description="API version")
 *     )
 * )
 */
class ValidationErrorSchema {}

/**
 * @OA\Schema(
 *     schema="HealthStatus",
 *     type="object",
 *     required={"status", "timestamp"},
 *     @OA\Property(property="status", type="string", enum={"healthy", "degraded", "unhealthy"}, example="healthy", description="Overall system health status"),
 *     @OA\Property(property="timestamp", type="string", format="date-time", example="2024-12-01T09:00:00Z", description="Health check timestamp"),
 *     @OA\Property(
 *         property="services",
 *         type="object",
 *         @OA\Property(property="database", type="string", enum={"connected", "disconnected", "error"}, example="connected", description="MySQL database status"),
 *         @OA\Property(property="mongodb", type="string", enum={"connected", "disconnected", "error"}, example="connected", description="MongoDB database status"),
 *         @OA\Property(property="cache", type="string", enum={"connected", "disconnected", "error"}, example="connected", description="Cache service status")
 *     ),
 *     @OA\Property(
 *         property="metrics",
 *         type="object",
 *         @OA\Property(property="uptime", type="integer", example=3600, description="System uptime in seconds"),
 *         @OA\Property(property="memory_usage", type="number", format="float", example=45.6, description="Memory usage percentage"),
 *         @OA\Property(property="response_time", type="number", format="float", example=125.5, description="Average response time in milliseconds")
 *     )
 * )
 */
class HealthStatusSchema {}