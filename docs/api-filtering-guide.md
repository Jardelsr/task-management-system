# Task Index Method - Advanced Filtering Documentation

## Endpoint: GET /tasks

The enhanced index method provides comprehensive filtering, sorting, and pagination capabilities for task listing.

## Available Query Parameters

### **Filtering Parameters**

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `status` | string | Filter by task status | `?status=pending` |
| `assigned_to` | integer | Filter by assigned user ID | `?assigned_to=123` |
| `created_by` | integer | Filter by creator user ID | `?created_by=456` |
| `overdue` | boolean | Filter overdue tasks only | `?overdue=true` |
| `with_due_date` | boolean | Filter tasks with due dates | `?with_due_date=true` |

### **Sorting Parameters**

| Parameter | Type | Default | Description | Valid Values |
|-----------|------|---------|-------------|--------------|
| `sort_by` | string | `created_at` | Field to sort by | `created_at`, `updated_at`, `due_date`, `title`, `status` |
| `sort_order` | string | `desc` | Sort direction | `asc`, `desc` |

### **Pagination Parameters**

| Parameter | Type | Default | Description | Constraints |
|-----------|------|---------|-------------|-------------|
| `limit` | integer | `50` | Results per page | Min: 1, Max: 1000 |
| `page` | integer | `1` | Page number | Min: 1 |

## Valid Status Values

- `pending`
- `in_progress`
- `completed`
- `cancelled`

## Example Requests

### Basic Status Filtering
```bash
GET /tasks?status=pending
```

### Advanced Filtering with Sorting
```bash
GET /tasks?status=in_progress&assigned_to=123&sort_by=due_date&sort_order=asc
```

### Pagination with Filters
```bash
GET /tasks?status=pending&page=2&limit=25
```

### Get Overdue Tasks
```bash
GET /tasks?overdue=true&sort_by=due_date&sort_order=asc
```

### Complex Filtering
```bash
GET /tasks?created_by=456&with_due_date=true&sort_by=created_at&limit=100
```

## Response Format

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Task Title",
            "description": "Task Description",
            "status": "pending",
            "created_by": 123,
            "assigned_to": 456,
            "due_date": "2025-09-25T15:30:00.000000Z",
            "completed_at": null,
            "created_at": "2025-09-20T10:00:00.000000Z",
            "updated_at": "2025-09-20T10:00:00.000000Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 50,
        "total": 150,
        "total_pages": 3,
        "has_next_page": true,
        "has_prev_page": false
    },
    "filters": {
        "status": "pending",
        "assigned_to": null,
        "created_by": null,
        "overdue": null,
        "with_due_date": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

## Error Responses

### Invalid Status
```json
{
    "error": "Invalid status parameter",
    "valid_statuses": ["pending", "in_progress", "completed", "cancelled"]
}
```

### Invalid Sort Field
```json
{
    "error": "Invalid sort_by parameter",
    "valid_sort_fields": ["created_at", "updated_at", "due_date", "title", "status"]
}
```

### Invalid Sort Order
```json
{
    "error": "Invalid sort_order parameter",
    "valid_sort_orders": ["asc", "desc"]
}
```

## Implementation Features

✅ **Repository Pattern** - Uses dependency injection with TaskRepositoryInterface  
✅ **Input Validation** - Validates all filter and sort parameters  
✅ **Pagination Support** - Full pagination with metadata  
✅ **Advanced Filtering** - Multiple filter criteria support  
✅ **Flexible Sorting** - Sort by multiple fields in both directions  
✅ **Error Handling** - Comprehensive error responses  
✅ **Performance Optimized** - Database-level filtering and sorting  
✅ **Backward Compatible** - Simple endpoint available at `/tasks/simple`  

## Performance Considerations

- All filtering is performed at the database level for optimal performance
- Pagination limits are enforced (max 1000 per request)
- Database indexes are in place for commonly filtered fields (status, assigned_to, created_by, due_date)
- Uses repository pattern to keep controller clean and testable