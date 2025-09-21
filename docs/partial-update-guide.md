# Partial Update Implementation Guide

## Overview

The Task Management System now supports comprehensive partial updates, allowing clients to update only specific fields of a task without affecting other data. This implementation ensures data integrity, proper validation, and detailed change tracking.

## Features

### 🔄 **Partial Field Updates**
- Update only the fields you specify in the request
- Automatically handles empty/null values appropriately
- Preserves existing data for fields not included in the request

### ✅ **Smart Validation**
- Validates only the fields provided in the request
- Uses "sometimes" validation rules for optional fields
- Maintains data integrity with proper field validation

### 🎯 **Automatic Status Management**
- Automatically sets `completed_at` when status changes to "completed"
- Clears `completed_at` when status changes from "completed" to other states
- Proper status transition validation

### 📝 **Change Detection & Logging**
- Detects which fields actually changed during the update
- Logs all changes to MongoDB for audit trail
- Provides detailed response messages about changes made

## API Usage

### Endpoint
```
PUT /tasks/{id}
```

### Partial Update Examples

#### 1. Update Only Title
```json
PUT /tasks/123
{
    "title": "Updated Task Title"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Task updated successfully. Changed fields: title",
    "data": {
        "id": 123,
        "title": "Updated Task Title",
        "description": "Original description remains",
        "status": "pending",
        ...
    }
}
```

#### 2. Update Status to Completed
```json
PUT /tasks/123
{
    "status": "completed"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Task updated successfully. Changed fields: status, completed_at",
    "data": {
        "id": 123,
        "status": "completed",
        "completed_at": "2025-09-20T15:30:00Z",
        ...
    }
}
```

#### 3. Multiple Field Update
```json
PUT /tasks/123
{
    "title": "New Title",
    "description": "New description",
    "assigned_to": 456
}
```

#### 4. Clear a Field (Set to Null)
```json
PUT /tasks/123
{
    "description": null,
    "assigned_to": null
}
```

## Validation Rules

### Field-Specific Validation
| Field | Validation Rules | Notes |
|-------|-----------------|-------|
| `title` | `sometimes\|required\|string\|max:255` | Required if provided |
| `description` | `sometimes\|nullable\|string\|max:1000` | Can be null |
| `status` | `sometimes\|required\|in:pending,in_progress,completed,cancelled` | Must be valid status |
| `assigned_to` | `sometimes\|nullable\|integer\|min:1` | Positive integer or null |
| `due_date` | `sometimes\|nullable\|date\|after:now` | Future date or null |
| `completed_at` | `sometimes\|nullable\|date` | Managed automatically |

### Automatic Validations
- Only validates fields that are actually provided in the request
- Ignores empty strings and treats them as "not provided"
- Handles null values explicitly when intentionally set

## Response Behavior

### Successful Updates
- **200 OK** - Task updated successfully
- Includes message showing which fields changed
- Returns complete updated task data

### No Changes Made
- **200 OK** - "Task update requested but no changes were made"
- Returns current task data unchanged

### No Valid Data Provided
- **200 OK** - "No valid data provided for update"
- Returns current task data when only empty/invalid fields sent

### Error Responses
- **404 Not Found** - Task doesn't exist
- **422 Validation Error** - Invalid data provided
- **500 Server Error** - Database or system error

## Change Detection

The system automatically detects which fields actually changed:

```json
{
    "message": "Task updated successfully. Changed fields: title, status, completed_at"
}
```

Fields are considered changed when:
- Value is different from current value
- Null is explicitly set to clear a field
- Date/time values differ (normalized comparison)

## Logging

All updates are automatically logged to MongoDB with:
- Task ID and timestamp
- Fields that changed
- Old and new values for each changed field
- Action type: "updated"

Example log entry:
```json
{
    "task_id": 123,
    "action": "updated",
    "changes": {
        "status": {
            "old": "pending",
            "new": "completed"
        },
        "completed_at": {
            "old": null,
            "new": "2025-09-20T15:30:00Z"
        }
    },
    "timestamp": "2025-09-20T15:30:00Z"
}
```

## Implementation Details

### Key Components

1. **UpdateTaskRequest** - Enhanced validation with partial update support
2. **ValidationHelper** - Utilities for filtering and preparing partial update data
3. **TaskController** - Smart update method with change detection
4. **TaskRepository** - Enhanced update method with logging

### Security Features

- Only allows updates to authorized fields
- Validates all provided data according to business rules
- Filters out unauthorized or invalid fields
- Maintains data integrity constraints

### Performance Considerations

- Minimal database queries (single update + fresh reload)
- Efficient change detection
- Asynchronous logging (doesn't block update operation)
- Smart data filtering reduces unnecessary processing

## Best Practices

### Client Implementation
```javascript
// Good: Only send fields you want to update
const partialUpdate = {
    title: "New Title"
};

// Avoid: Sending full object with unchanged fields
const fullUpdate = {
    title: "New Title",
    description: existingTask.description, // unnecessary
    status: existingTask.status,           // unnecessary
    // ... other unchanged fields
};
```

### Field Management
- Send `null` explicitly to clear optional fields
- Don't send empty strings - they're treated as "not provided"
- Use proper data types (integers for IDs, ISO dates for timestamps)

### Error Handling
```javascript
try {
    const response = await updateTask(taskId, partialData);
    console.log('Changed fields:', response.message);
} catch (error) {
    if (error.status === 422) {
        // Handle validation errors
        console.error('Validation failed:', error.errors);
    }
}
```

This partial update implementation provides a robust, flexible, and user-friendly way to modify tasks while maintaining data integrity and providing comprehensive audit trails.