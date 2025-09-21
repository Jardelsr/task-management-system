# Error Handling System Documentation

## Overview

The Task Management System implements a comprehensive error handling structure that provides consistent, informative error responses across the entire API. This system includes custom exceptions, centralized error responses, and proper logging.

## Custom Exception Classes

### TaskNotFoundException
- **Purpose**: Thrown when a requested task cannot be found
- **HTTP Status**: 404
- **Usage**: `throw new TaskNotFoundException($taskId);`
- **Response Format**:
```json
{
  "error": "Task not found",
  "message": "Task with ID 123 not found",
  "task_id": 123,
  "code": "TASK_NOT_FOUND"
}
```

### TaskValidationException
- **Purpose**: Thrown when task data validation fails
- **HTTP Status**: 422
- **Usage**: `throw new TaskValidationException($errors, $field);`
- **Response Format**:
```json
{
  "error": "Validation failed",
  "message": "Task validation failed",
  "errors": {"title": ["The title field is required"]},
  "field": "title",
  "code": "VALIDATION_FAILED"
}
```

### DatabaseException
- **Purpose**: Thrown when database operations fail
- **HTTP Status**: 500
- **Usage**: `throw new DatabaseException($message, $operation, $context);`
- **Response Format**:
```json
{
  "error": "Database operation failed",
  "message": "Failed to connect to database",
  "operation": "select",
  "code": "DATABASE_ERROR"
}
```

### TaskOperationException
- **Purpose**: Thrown when task operations fail
- **HTTP Status**: 500
- **Usage**: `throw new TaskOperationException($message, $operation, $taskId);`
- **Response Format**:
```json
{
  "error": "Task operation failed",
  "message": "Failed to update task status",
  "operation": "update",
  "task_id": 123,
  "code": "TASK_OPERATION_FAILED"
}
```

### LoggingException
- **Purpose**: Thrown when logging operations fail
- **HTTP Status**: 500
- **Usage**: `throw new LoggingException($message, $operation, $context);`

## Error Response Helper (ErrorResponseTrait)

The `ErrorResponseTrait` provides consistent methods for generating error responses:

### Available Methods

- `errorResponse($error, $message, $statusCode, $details, $code)` - Generic error response
- `validationErrorResponse($errors, $message)` - Validation error response
- `notFoundResponse($resource, $id)` - Resource not found response
- `databaseErrorResponse($operation, $message)` - Database error response
- `unauthorizedResponse($message)` - 401 Unauthorized response
- `forbiddenResponse($message)` - 403 Forbidden response
- `serverErrorResponse($message, $details)` - 500 Server error response
- `successResponse($data, $message, $statusCode)` - Success response

### Usage Example

```php
// In a controller
public function someMethod()
{
    try {
        // Some operation
    } catch (TaskNotFoundException $e) {
        throw $e; // Let exception handler deal with it
    } catch (\Exception $e) {
        return $this->serverErrorResponse('Operation failed');
    }
}
```

## Exception Handler (app/Exceptions/Handler.php)

The enhanced exception handler provides:

1. **Automatic Exception Mapping**: Maps custom exceptions to appropriate HTTP responses
2. **Consistent Response Format**: All errors follow the same JSON structure
3. **Logging Integration**: Automatically logs exceptions with context
4. **Debug Mode Support**: Shows detailed error information when `APP_DEBUG=true`
5. **Security**: Hides sensitive information in production mode

### Response Format

All error responses follow this structure:

```json
{
  "success": false,
  "error": "Error type",
  "message": "Human-readable error message",
  "timestamp": "2025-09-20T10:30:00.000000Z",
  "details": {
    // Additional error-specific details
  },
  "code": "ERROR_CODE",
  "debug": {
    // Debug information (only in debug mode)
    "file": "/path/to/file.php",
    "line": 42
  }
}
```

## Configuration

Error handling is configured in `config/errors.php`:

- **Default Messages**: Predefined error messages
- **Error Codes**: Standardized error codes
- **Logging**: Logging behavior configuration
- **Debug**: Debug mode settings
- **Rate Limiting**: Error response rate limiting

## Best Practices

### 1. Use Specific Exceptions
```php
// Good
throw new TaskNotFoundException($id);

// Avoid
throw new \Exception('Task not found');
```

### 2. Provide Context
```php
// Good
throw new DatabaseException(
    'Failed to update task',
    'update',
    ['task_id' => $id, 'fields' => $data]
);

// Less helpful
throw new DatabaseException('Update failed');
```

### 3. Let Exception Handler Deal with Responses
```php
// Good
public function show($id)
{
    $task = $this->repository->findById($id);
    
    if (!$task) {
        throw new TaskNotFoundException($id);
    }
    
    return $this->successResponse($task);
}

// Avoid manual response creation
public function show($id)
{
    try {
        $task = $this->repository->findById($id);
        
        if (!$task) {
            return response()->json(['error' => 'Not found'], 404);
        }
        
        return response()->json($task);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed'], 500);
    }
}
```

### 4. Use Response Helper Methods
```php
// Good
return $this->successResponse($data, 'Task created successfully', 201);

// Less consistent
return response()->json(['success' => true, 'data' => $data], 201);
```

## Testing Error Handling

### Example Test Cases

```php
/** @test */
public function it_throws_task_not_found_exception_when_task_does_not_exist()
{
    $this->expectException(TaskNotFoundException::class);
    $this->controller->show(999);
}

/** @test */
public function it_returns_proper_error_response_for_not_found_task()
{
    $response = $this->get('/tasks/999');
    
    $response->assertStatus(404)
             ->assertJson([
                 'success' => false,
                 'error' => 'Task not found',
                 'code' => 'TASK_NOT_FOUND'
             ]);
}
```

## Monitoring and Logging

The error handling system automatically logs:
- Database exceptions with operation context
- Task operation failures with task IDs
- Validation errors (when configured)
- All unhandled exceptions

Logs are structured for easy monitoring:
```
[2025-09-20 10:30:00] local.ERROR: Database operation failed
{
  "operation": "update",
  "context": {"task_id": 123},
  "message": "Connection timeout",
  "file": "/app/TaskController.php",
  "line": 45
}
```

This comprehensive error handling system ensures consistent, informative, and secure error responses throughout the Task Management System API.