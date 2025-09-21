# Enhanced Response Formatting Documentation

## Overview

The Task Management System now implements a comprehensive and consistent response formatting system that provides standardized success and error responses across all API endpoints.

## Response Formatting Traits

### 1. SuccessResponseTrait

This trait provides methods for consistent success response formatting.

#### Methods:

##### `successResponse($data, $message, $statusCode, $meta)`
Creates a standardized success response.

**Example Response:**
```json
{
  "success": true,
  "timestamp": "2025-09-20T15:30:00.000000Z",
  "message": "Operation completed successfully",
  "data": { ... },
  "meta": { ... }
}
```

##### `paginatedResponse($data, $pagination, $message)`
Creates a paginated success response with comprehensive pagination metadata.

**Example Response:**
```json
{
  "success": true,
  "timestamp": "2025-09-20T15:30:00.000000Z",
  "message": "Filtered tasks retrieved successfully",
  "data": [...],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 50,
      "total": 150,
      "total_pages": 3,
      "from": 1,
      "to": 50,
      "has_more": true
    }
  }
}
```

##### `createdResponse($data, $message)`
Returns 201 status for resource creation.

##### `updatedResponse($data, $message)`
Returns 200 status for resource updates.

##### `deletedResponse($message, $meta)`
Returns success response for deletions.

##### `statsResponse($stats, $message)`
Specialized response for statistics endpoints.

##### `taskOperationResponse($data, $action, $message)`
Task-specific operation responses with operation metadata.

##### `logResponse($data, $message, $filters)`
Log-specific responses with filter metadata.

##### `bulkOperationResponse($results, $message)`
Handles bulk operations with success/failure counts.

### 2. ErrorResponseTrait

Enhanced error response formatting with detailed error information.

#### Methods:

##### `errorResponse($error, $message, $statusCode, $details, $code)`
Creates standardized error responses.

**Example Response:**
```json
{
  "success": false,
  "error": "Task not found",
  "message": "Task with ID 123 not found",
  "timestamp": "2025-09-20T15:30:00.000000Z",
  "code": "TASK_NOT_FOUND",
  "details": {
    "task_id": 123
  }
}
```

##### `validationErrorResponse($errors, $message)`
Specialized validation error formatting.

**Example Response:**
```json
{
  "success": false,
  "error": "Validation failed",
  "message": "Task validation failed",
  "timestamp": "2025-09-20T15:30:00.000000Z",
  "code": "VALIDATION_FAILED",
  "details": {
    "errors": {
      "title": ["The title field is required"],
      "status": ["The selected status is invalid"]
    }
  }
}
```

##### Additional Error Methods:
- `notFoundResponse($resource, $id)`
- `databaseErrorResponse($operation, $message)`
- `unauthorizedResponse($message)`
- `forbiddenResponse($message)`
- `methodNotAllowedResponse($method, $allowedMethods)`
- `conflictResponse($message, $details)`
- `tooManyRequestsResponse($message, $retryAfter)`
- `unprocessableEntityResponse($message, $errors)`
- `serviceUnavailableResponse($message, $retryAfter)`
- `exceptionResponse($exception, $context)`

## Response Standards

### Success Responses

All success responses include:
- `success: true`
- `timestamp`: ISO 8601 formatted timestamp
- `message`: Optional descriptive message
- `data`: Response data (if applicable)
- `meta`: Optional metadata (pagination, filters, etc.)

### Error Responses

All error responses include:
- `success: false`
- `error`: Error type/category
- `message`: Human-readable error message
- `timestamp`: ISO 8601 formatted timestamp
- `code`: Machine-readable error code
- `details`: Additional error context (optional)

### HTTP Status Codes

#### Success Codes:
- `200`: OK - Standard success
- `201`: Created - Resource creation
- `204`: No Content - No response body needed

#### Error Codes:
- `400`: Bad Request - Invalid request format
- `401`: Unauthorized - Authentication required
- `403`: Forbidden - Insufficient permissions
- `404`: Not Found - Resource doesn't exist
- `405`: Method Not Allowed - HTTP method not supported
- `409`: Conflict - Resource conflict
- `422`: Unprocessable Entity - Validation errors
- `429`: Too Many Requests - Rate limiting
- `500`: Internal Server Error - Server errors
- `503`: Service Unavailable - Temporary unavailability

## Implementation Examples

### TaskController Examples

#### Task Creation:
```php
// Success
return $this->taskOperationResponse($task->toArray(), 'created');

// Validation Error (thrown exception)
throw new TaskValidationException($errors);
```

#### Task Listing with Pagination:
```php
$paginationData = [
    'current_page' => $page,
    'per_page' => $limit,
    'total' => $totalCount,
    'from' => $offset + 1,
    'to' => min($offset + $limit, $totalCount),
    'has_more' => $page < ceil($totalCount / $limit)
];

return $this->paginatedResponse($tasks, $paginationData, $message);
```

#### Task Statistics:
```php
return $this->statsResponse($stats);
```

### LogController Examples

#### Log Retrieval:
```php
return $this->logResponse(
    $logs,
    'Recent logs retrieved successfully',
    ['limit' => $limit, 'count' => $logs->count()]
);
```

#### Log Not Found:
```php
throw new LoggingException(
    'Log not found',
    'find_by_id',
    ['log_id' => $id]
);
```

## Error Handling Integration

The enhanced response system integrates with the existing exception handling:

1. **Controllers** throw specific exceptions instead of returning error responses directly
2. **Exception Handler** catches exceptions and formats them using the traits
3. **Custom Exceptions** provide structured error details
4. **Logging** captures errors for debugging while providing clean user responses

## Benefits

1. **Consistency**: All endpoints return standardized response formats
2. **Predictability**: Clients can rely on consistent response structure
3. **Debugging**: Rich error details and logging for development
4. **Maintainability**: Centralized response formatting logic
5. **Extensibility**: Easy to add new response types
6. **Standards Compliance**: Follows REST API best practices

## Migration Notes

The system maintains backward compatibility while enhancing response formatting. All endpoints now return more detailed and consistent responses with additional metadata for better client integration.