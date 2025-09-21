# Enhanced Response Formatting Documentation

## Overview

The Task Management System now features a comprehensive and consistent response formatting system that provides standardized success and error responses across all API endpoints with enhanced metadata, pagination, filtering, and debugging capabilities.

## 🚀 Key Features

### Enhanced Response Structure
- **Consistent format** across all endpoints
- **Rich metadata** including request tracking and performance metrics
- **Comprehensive pagination** with navigation links
- **Security headers** for protection
- **Request/Response tracking** for debugging
- **API versioning** support

### Response Components
- **Status indicators** (`success` field)
- **Timestamps** (ISO 8601 format)
- **Request metadata** (request ID, execution time, API version)
- **Pagination metadata** (current page, total pages, navigation)
- **Filter metadata** (applied filters, counts)
- **Security headers** (XSS protection, content type options)

---

## 📊 Success Response Format

### Basic Success Response
```json
{
  "success": true,
  "timestamp": "2025-09-20T10:30:45.123Z",
  "message": "Operation completed successfully",
  "data": {
    // Response data here
  },
  "meta": {
    "request_id": "req_66ed123456789",
    "api_version": "1.0",
    "execution_time": "45.67ms"
  }
}
```

### Paginated Response Format
```json
{
  "success": true,
  "timestamp": "2025-09-20T10:30:45.123Z",
  "message": "Tasks retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Sample Task",
      "status": "pending",
      "created_at": "2025-09-20T09:00:00.000Z"
    }
    // ... more items
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 150,
    "total_pages": 3,
    "has_next_page": true,
    "has_previous_page": false,
    "next_page": 2,
    "previous_page": null
  },
  "meta": {
    "applied_filters": {
      "status": "pending",
      "sort_by": "created_at",
      "sort_order": "desc"
    },
    "request_id": "req_66ed123456789",
    "api_version": "1.0",
    "execution_time": "123.45ms"
  }
}
```

---

## ❌ Error Response Format

### Basic Error Response
```json
{
  "success": false,
  "error": "Task not found",
  "message": "The requested task with ID 999 could not be found",
  "timestamp": "2025-09-20T10:30:45.123Z",
  "code": "TASK_NOT_FOUND",
  "details": {
    "task_id": 999,
    "operation": "show",
    "suggestions": [
      "Verify the task ID is correct",
      "Check if the task was deleted",
      "Use GET /tasks to list all available tasks"
    ]
  }
}
```

### Validation Error Response
```json
{
  "success": false,
  "error": "Validation failed",
  "message": "The given data was invalid",
  "timestamp": "2025-09-20T10:30:45.123Z",
  "code": "VALIDATION_ERROR",
  "details": {
    "errors": {
      "title": [
        "The task title is required.",
        "The task title must be at least 3 characters."
      ],
      "status": [
        "The selected status is invalid."
      ]
    },
    "failed_fields": ["title", "status"]
  }
}
```

---

## 🔗 API Endpoints and Responses

### Task Endpoints

#### **GET /tasks** - List Tasks with Filtering
**Query Parameters:**
- `status` - Filter by task status
- `assigned_to` - Filter by assigned user ID
- `created_by` - Filter by creator user ID
- `overdue` - Filter overdue tasks (boolean)
- `with_due_date` - Filter tasks with due dates (boolean)
- `sort_by` - Sort field (created_at, updated_at, due_date, title, status)
- `sort_order` - Sort direction (asc, desc)
- `limit` - Items per page (1-1000, default: 50)
- `page` - Page number (default: 1)

**Example Request:**
```bash
GET /tasks?status=pending&sort_by=created_at&sort_order=desc&limit=25&page=1
```

**Example Response:**
```json
{
  "success": true,
  "timestamp": "2025-09-20T10:30:45.123Z",
  "message": "Tasks retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Complete API documentation",
      "description": "Document all API endpoints with examples",
      "status": "pending",
      "created_by": 1,
      "assigned_to": 2,
      "due_date": "2025-09-25T17:00:00.000Z",
      "completed_at": null,
      "created_at": "2025-09-20T09:00:00.000Z",
      "updated_at": "2025-09-20T09:00:00.000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 25,
    "total": 42,
    "total_pages": 2,
    "has_next_page": true,
    "has_previous_page": false,
    "next_page": 2,
    "previous_page": null
  },
  "meta": {
    "applied_filters": {
      "status": "pending",
      "sort_by": "created_at",
      "sort_order": "desc"
    },
    "data_type": "collection",
    "data_count": 1,
    "request_id": "req_66ed123456789",
    "api_version": "1.0",
    "execution_time": "67.89ms"
  }
}
```

**Response Headers:**
```
X-API-Version: 1.0
X-Request-ID: req_66ed123456789
X-Execution-Time: 67.89ms
X-Timestamp: 2025-09-20T10:30:45.123Z
X-Total-Count: 42
X-Page: 1
X-Per-Page: 25
X-Total-Pages: 2
Cache-Control: no-cache, private
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
```

#### **GET /tasks/{id}** - Get Specific Task
**Example Response:**
```json
{
  "success": true,
  "timestamp": "2025-09-20T10:30:45.123Z",
  "message": "Task retrieved successfully",
  "data": {
    "id": 1,
    "title": "Complete API documentation",
    "description": "Document all API endpoints with examples",
    "status": "pending",
    "created_by": 1,
    "assigned_to": 2,
    "due_date": "2025-09-25T17:00:00.000Z",
    "completed_at": null,
    "created_at": "2025-09-20T09:00:00.000Z",
    "updated_at": "2025-09-20T09:00:00.000Z"
  },
  "meta": {
    "data_type": "object",
    "request_id": "req_66ed123456789",
    "api_version": "1.0",
    "execution_time": "23.45ms"
  }
}
```

#### **POST /tasks** - Create New Task
**Example Request Body:**
```json
{
  "title": "New Task",
  "description": "Task description",
  "status": "pending",
  "assigned_to": 2,
  "due_date": "2025-09-25T17:00:00.000Z"
}
```

**Example Response (201 Created):**
```json
{
  "success": true,
  "timestamp": "2025-09-20T10:30:45.123Z",
  "message": "Task created successfully",
  "data": {
    "id": 5,
    "title": "New Task",
    "description": "Task description",
    "status": "pending",
    "created_by": null,
    "assigned_to": 2,
    "due_date": "2025-09-25T17:00:00.000Z",
    "completed_at": null,
    "created_at": "2025-09-20T10:30:45.000Z",
    "updated_at": "2025-09-20T10:30:45.000Z"
  },
  "meta": {
    "operation": "created",
    "request_id": "req_66ed123456789",
    "api_version": "1.0",
    "execution_time": "45.67ms"
  }
}
```

#### **PUT /tasks/{id}** - Update Task
**Example Request Body (Partial Update):**
```json
{
  "status": "completed"
}
```

**Example Response:**
```json
{
  "success": true,
  "timestamp": "2025-09-20T10:30:45.123Z",
  "message": "Task updated successfully. Changed fields: status, completed_at",
  "data": {
    "id": 1,
    "title": "Complete API documentation",
    "description": "Document all API endpoints with examples",
    "status": "completed",
    "created_by": 1,
    "assigned_to": 2,
    "due_date": "2025-09-25T17:00:00.000Z",
    "completed_at": "2025-09-20T10:30:45.000Z",
    "created_at": "2025-09-20T09:00:00.000Z",
    "updated_at": "2025-09-20T10:30:45.000Z"
  },
  "meta": {
    "request_id": "req_66ed123456789",
    "api_version": "1.0",
    "execution_time": "78.90ms"
  }
}
```

**Response Headers:**
```
X-Validation-Version: 2.0
X-Changed-Fields: status,completed_at
```

#### **DELETE /tasks/{id}** - Delete Task
**Example Response (204 No Content):**
```json
{
  "success": true,
  "timestamp": "2025-09-20T10:30:45.123Z",
  "message": "Task deleted successfully",
  "meta": {
    "request_id": "req_66ed123456789",
    "api_version": "1.0",
    "execution_time": "34.56ms"
  }
}
```

### Log Endpoints

#### **GET /logs** - List Logs with Filtering
**Query Parameters:**
- `id` - Specific log ID (MongoDB ObjectId)
- `limit` - Items per page (1-100, default: 50)
- `page` - Page number (default: 1)
- `task_id` - Filter by task ID
- `action` - Filter by action (created, updated, deleted)
- `user_id` - Filter by user ID

**Example Request:**
```bash
GET /logs?task_id=1&action=updated&limit=20&page=1
```

**Example Response:**
```json
{
  "success": true,
  "timestamp": "2025-09-20T10:30:45.123Z",
  "message": "Logs retrieved successfully",
  "data": [
    {
      "_id": "66ed123456789abcdef012345",
      "task_id": 1,
      "action": "updated",
      "old_data": {
        "status": "pending"
      },
      "new_data": {
        "status": "completed",
        "completed_at": "2025-09-20T10:30:45.000Z"
      },
      "user_id": 1,
      "user_name": "John Doe",
      "created_at": "2025-09-20T10:30:45.000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 5,
    "total_pages": 1,
    "has_next_page": false,
    "has_previous_page": false
  },
  "meta": {
    "applied_filters": {
      "task_id": 1,
      "action": "updated"
    },
    "resource_type": "logs",
    "count": 1,
    "request_id": "req_66ed123456789",
    "api_version": "1.0",
    "execution_time": "56.78ms"
  }
}
```

#### **GET /tasks/{id}/logs** - Get Logs for Specific Task
**Example Response:**
```json
{
  "success": true,
  "timestamp": "2025-09-20T10:30:45.123Z",
  "message": "Logs for task 1 retrieved successfully",
  "data": [
    {
      "_id": "66ed123456789abcdef012345",
      "task_id": 1,
      "action": "created",
      "old_data": {},
      "new_data": {
        "title": "Complete API documentation",
        "status": "pending"
      },
      "user_id": 1,
      "user_name": "John Doe",
      "created_at": "2025-09-20T09:00:00.000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 3,
    "total_pages": 1,
    "has_next_page": false,
    "has_previous_page": false
  }
}
```

**Response Headers:**
```
X-Task-ID: 1
X-Total-Count: 3
X-API-Version: 1.0
```

---

## 📋 Response Headers Reference

### Standard Headers (All Responses)
| Header | Description | Example |
|--------|-------------|---------|
| `X-API-Version` | API version number | `1.0` |
| `X-Request-ID` | Unique request identifier | `req_66ed123456789` |
| `X-Execution-Time` | Request processing time | `45.67ms` |
| `X-Timestamp` | Response generation time | `2025-09-20T10:30:45.123Z` |
| `Cache-Control` | Caching directives | `no-cache, private` |
| `X-Content-Type-Options` | Content type security | `nosniff` |
| `X-Frame-Options` | Frame embedding security | `DENY` |
| `X-XSS-Protection` | XSS protection | `1; mode=block` |

### Pagination Headers
| Header | Description | Example |
|--------|-------------|---------|
| `X-Total-Count` | Total number of items | `150` |
| `X-Page` | Current page number | `1` |
| `X-Per-Page` | Items per page | `50` |
| `X-Total-Pages` | Total number of pages | `3` |

### Operation-Specific Headers
| Header | Description | Example |
|--------|-------------|---------|
| `X-Changed-Fields` | Modified fields in update | `status,completed_at` |
| `X-Validation-Version` | Validation system version | `2.0` |
| `X-Task-ID` | Related task ID | `1` |

---

## ⚙️ Configuration Options

### API Configuration (`config/api.php`)
```php
'version' => '1.0',
'responses' => [
    'include_execution_time' => true,
    'include_request_id' => true,
    'include_debug_info' => false,
    'max_per_page' => 1000,
    'default_per_page' => 50,
],
'headers' => [
    'version_header' => 'X-API-Version',
    'request_id_header' => 'X-Request-ID',
    'execution_time_header' => 'X-Execution-Time',
    'total_count_header' => 'X-Total-Count',
]
```

---

## 🔧 Usage Examples

### Client-Side Handling

#### JavaScript/Fetch Example
```javascript
async function fetchTasks(filters = {}) {
  const params = new URLSearchParams(filters);
  const requestId = `req_${Date.now()}_${Math.random()}`;
  
  const response = await fetch(`/tasks?${params}`, {
    headers: {
      'X-Request-ID': requestId,
      'Content-Type': 'application/json'
    }
  });
  
  const data = await response.json();
  
  if (data.success) {
    console.log(`Request ${data.meta.request_id} completed in ${data.meta.execution_time}`);
    console.log(`Total items: ${data.pagination.total}`);
    return data;
  } else {
    console.error(`Error ${data.code}: ${data.message}`);
    throw new Error(data.message);
  }
}

// Usage
fetchTasks({ status: 'pending', limit: 25 })
  .then(response => {
    console.log('Tasks:', response.data);
    console.log('Pagination:', response.pagination);
  })
  .catch(error => console.error('Failed:', error));
```

#### Pagination Navigation Example
```javascript
function buildPaginationLinks(pagination) {
  const links = [];
  
  if (pagination.has_previous_page) {
    links.push({
      rel: 'prev',
      page: pagination.previous_page,
      label: 'Previous'
    });
  }
  
  if (pagination.has_next_page) {
    links.push({
      rel: 'next',
      page: pagination.next_page,
      label: 'Next'
    });
  }
  
  return links;
}
```

### Error Handling Best Practices
```javascript
function handleApiResponse(response) {
  if (!response.success) {
    // Log error details
    console.error('API Error:', {
      code: response.code,
      message: response.message,
      details: response.details,
      timestamp: response.timestamp
    });
    
    // Handle specific error types
    switch (response.code) {
      case 'TASK_NOT_FOUND':
        showNotFoundMessage(response.details.suggestions);
        break;
      case 'VALIDATION_ERROR':
        showValidationErrors(response.details.errors);
        break;
      case 'RATE_LIMIT_EXCEEDED':
        const retryAfter = response.details.retry_after;
        scheduleRetry(retryAfter);
        break;
      default:
        showGenericError(response.message);
    }
    
    return false;
  }
  
  return true;
}
```

---

## 🚀 Performance Features

### Request Tracking
- Unique request IDs for tracing
- Execution time measurement
- Performance header inclusion

### Caching Headers
- Appropriate cache control directives
- ETags for resource versioning
- Last-Modified headers

### Security Headers
- XSS protection
- Content type options
- Frame options
- CSRF protection ready

---

## 📈 Monitoring and Debugging

### Request Tracking
Each response includes a unique `request_id` that can be used for:
- Log correlation
- Performance monitoring
- Issue debugging
- Request tracing

### Performance Metrics
The `execution_time` field provides:
- Response time measurement
- Performance bottleneck identification
- API optimization insights

### Error Correlation
Error responses include:
- Detailed error codes
- Contextual information
- Helpful suggestions
- Operation context

---

This enhanced response formatting system provides a robust, consistent, and developer-friendly API experience with comprehensive metadata, security features, and debugging capabilities.