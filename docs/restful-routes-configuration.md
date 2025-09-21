# RESTful Routes Configuration - Task Management System

## Overview

The Task Management System implements a comprehensive RESTful API with both versioned (`/api/v1`) and direct routes for backward compatibility. This configuration follows REST conventions while providing advanced features like soft deletion, filtering, and pagination.

## 🚀 Route Structure

### **Health Check & Root Routes**

| Method | URI | Description |
|--------|-----|-------------|
| `GET` | `/` | Health check and API overview |

**Response Example:**
```json
{
  "message": "Task Management System API",
  "version": "Lumen (11.0)",
  "status": "active",
  "timestamp": "2025-09-20T21:30:00Z",
  "endpoints": {
    "tasks": "http://localhost:8000/tasks",
    "logs": "http://localhost:8000/logs",
    "api_v1": "http://localhost:8000/api/v1",
    "documentation": "http://localhost:8000/api/v1/docs"
  }
}
```

---

## 📋 Task Resource Routes

### **Versioned API Routes (v1)**

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| `GET` | `/api/v1/tasks` | `TaskController@index` | List all tasks with filtering/pagination |
| `POST` | `/api/v1/tasks` | `TaskController@store` | Create a new task |
| `GET` | `/api/v1/tasks/{id}` | `TaskController@show` | Show a specific task |
| `PUT` | `/api/v1/tasks/{id}` | `TaskController@update` | Update a task (full replacement) |
| `PATCH` | `/api/v1/tasks/{id}` | `TaskController@update` | Update a task (partial update) |
| `DELETE` | `/api/v1/tasks/{id}` | `TaskController@destroy` | Soft delete a task |

### **Collection Routes**

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| `GET` | `/api/v1/tasks/stats` | `TaskController@stats` | Get task statistics |
| `GET` | `/api/v1/tasks/trashed` | `TaskController@trashed` | List soft-deleted tasks |

### **Operation Routes**

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| `POST` | `/api/v1/tasks/{id}/restore` | `TaskController@restore` | Restore a soft-deleted task |
| `DELETE` | `/api/v1/tasks/{id}/force` | `TaskController@forceDelete` | Permanently delete a task |

---

## 📝 Direct Routes (Backward Compatibility)

All the same routes are available without the `/api/v1` prefix:

| Method | URI | Description |
|--------|-----|-------------|
| `GET` | `/tasks` | List all tasks |
| `POST` | `/tasks` | Create a new task |
| `GET` | `/tasks/{id}` | Show a specific task |
| `PUT/PATCH` | `/tasks/{id}` | Update a task |
| `DELETE` | `/tasks/{id}` | Soft delete a task |
| `GET` | `/tasks/stats` | Get task statistics |
| `GET` | `/tasks/trashed` | List soft-deleted tasks |
| `POST` | `/tasks/{id}/restore` | Restore a soft-deleted task |
| `DELETE` | `/tasks/{id}/force` | Permanently delete a task |

---

## 📊 Log Resource Routes

### **Versioned API Routes (v1)**

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| `GET` | `/api/v1/logs` | `LogController@index` | List recent logs |
| `GET` | `/api/v1/logs/stats` | `LogController@stats` | Get log statistics |
| `GET` | `/api/v1/logs/tasks/{id}` | `LogController@taskLogs` | Get logs for a specific task |

### **Direct Route**

| Method | URI | Controller Method | Description |
|--------|-----|-------------------|-------------|
| `GET` | `/logs` | `LogController@index` | List recent logs (backward compatibility) |

---

## 🔧 Advanced Features

### **Filtering & Querying**

The `GET /tasks` endpoint supports advanced filtering:

```http
GET /tasks?status=pending&assigned_to=123&sort=created_at&direction=desc&limit=20
```

**Available Parameters:**
- `status` - Filter by task status (pending, in_progress, completed, cancelled)
- `assigned_to` - Filter by assigned user ID
- `created_by` - Filter by creator user ID
- `overdue` - Filter overdue tasks (true/false)
- `with_due_date` - Filter tasks with due dates (true/false)
- `sort` - Sort field (any task field)
- `direction` - Sort direction (asc/desc)
- `limit` - Number of results per page
- `offset` - Pagination offset

### **Soft Delete System**

The system implements comprehensive soft delete functionality:

1. **Soft Delete**: `DELETE /tasks/{id}` - Marks task as deleted
2. **Restore**: `POST /tasks/{id}/restore` - Restores soft-deleted task
3. **Force Delete**: `DELETE /tasks/{id}/force` - Permanently removes task
4. **List Trashed**: `GET /tasks/trashed` - Shows soft-deleted tasks

### **Partial Updates**

The `PATCH /tasks/{id}` endpoint supports partial updates:

```json
PATCH /tasks/123
{
  "status": "completed"
}
```

Only provided fields are validated and updated, preserving existing data.

---

## 📚 API Documentation Route

### **Interactive Documentation**

| Method | URI | Description |
|--------|-----|-------------|
| `GET` | `/api/v1/docs` | Comprehensive API documentation |

**Response includes:**
- Complete endpoint listing
- Resource descriptions
- Available operations
- Feature overview
- Base URLs and versions

---

## 🚨 Error Handling

### **404 - Route Not Found**

Any undefined route returns a structured 404 response:

```json
{
  "error": "Route not found",
  "message": "The requested endpoint does not exist",
  "available_endpoints": {
    "tasks": "http://localhost:8000/tasks",
    "logs": "http://localhost:8000/logs",
    "api": "http://localhost:8000/api/v1",
    "documentation": "http://localhost:8000/api/v1/docs"
  }
}
```

---

## 🔧 Route Configuration Best Practices

### **1. Route Order Priority**

Collection routes (like `/stats`, `/trashed`) are placed **before** resource routes to prevent conflicts with route parameter matching.

```php
// ✅ Correct order
$router->get('/stats', 'TaskController@stats');           // Collection route first
$router->get('/{id:[0-9]+}', 'TaskController@show');      // Resource route second

// ❌ Wrong order would cause conflicts
```

### **2. Parameter Constraints**

All ID parameters use regex constraints to ensure only numeric IDs are accepted:

```php
$router->get('/{id:[0-9]+}', 'TaskController@show');
```

### **3. HTTP Method Semantics**

- `GET` - Retrieve data (safe, idempotent)
- `POST` - Create new resources or trigger operations
- `PUT` - Full replacement of resources (idempotent)
- `PATCH` - Partial update of resources
- `DELETE` - Remove resources

### **4. RESTful Resource Naming**

- Use plural nouns for resources (`tasks`, not `task`)
- Use consistent naming conventions
- Group related operations logically

---

## 🧪 Testing Routes

### **Basic Route Testing**

```bash
# Health check
curl -X GET http://localhost:8000/

# List tasks
curl -X GET http://localhost:8000/tasks

# Create task
curl -X POST http://localhost:8000/tasks \
  -H "Content-Type: application/json" \
  -d '{"title":"Test Task","description":"Test Description","status":"pending"}'

# Get task
curl -X GET http://localhost:8000/tasks/1

# Update task
curl -X PUT http://localhost:8000/tasks/1 \
  -H "Content-Type: application/json" \
  -d '{"title":"Updated Task","status":"completed"}'

# Soft delete
curl -X DELETE http://localhost:8000/tasks/1

# Restore task
curl -X POST http://localhost:8000/tasks/1/restore

# Get documentation
curl -X GET http://localhost:8000/api/v1/docs
```

---

## 📋 Summary

The RESTful routes configuration provides:

✅ **Complete REST compliance** with proper HTTP methods  
✅ **Dual API support** - versioned and direct routes  
✅ **Advanced features** - filtering, pagination, sorting  
✅ **Soft delete system** with restore capabilities  
✅ **Comprehensive documentation** built into the API  
✅ **Error handling** with helpful 404 responses  
✅ **Route optimization** with proper ordering and constraints  
✅ **Backward compatibility** maintained throughout  

The system is production-ready and follows RESTful best practices while providing advanced functionality for modern web applications.