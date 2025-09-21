# Soft Delete Implementation - Task Management System

## Overview

The Task Management System now includes comprehensive soft delete functionality, allowing tasks to be "deleted" without permanent removal from the database. This provides data recovery capabilities and maintains audit trails.

## 🔄 Soft Delete Features

### **Core Functionality**
- **Soft Delete**: Mark tasks as deleted without removing from database
- **Restore**: Recover soft-deleted tasks to active state  
- **Force Delete**: Permanently remove tasks from database
- **Trashed Tasks**: List all soft-deleted tasks
- **Comprehensive Logging**: All operations are logged to MongoDB

### **Database Schema**
- Uses Laravel's `SoftDeletes` trait
- `deleted_at` timestamp column tracks deletion time
- Soft-deleted records are automatically excluded from normal queries

## 📡 API Endpoints

### **Soft Delete Task**
```http
DELETE /tasks/{id}
```
- Marks task as deleted (sets `deleted_at` timestamp)
- Task becomes inaccessible via normal queries
- Logs action as 'deleted' in MongoDB

**Response**: 200 OK
```json
{
  "success": true,
  "message": "Task deleted successfully",
  "timestamp": "2025-09-20T15:30:45Z"
}
```

### **Restore Task**
```http
POST /tasks/{id}/restore
```
- Restores soft-deleted task to active state
- Clears `deleted_at` timestamp
- Logs action as 'restored' in MongoDB

**Response**: 200 OK
```json
{
  "success": true,
  "message": "Task restored successfully",
  "data": {
    "id": 123,
    "title": "Restored Task",
    "status": "pending",
    "deleted_at": null,
    "updated_at": "2025-09-20T15:30:45Z"
  }
}
```

### **Force Delete Task**
```http
DELETE /tasks/{id}/force
```
- Permanently removes task from database
- Works on both active and soft-deleted tasks
- Logs action as 'force_deleted' in MongoDB
- **⚠️ Warning**: This action cannot be undone

**Response**: 200 OK
```json
{
  "success": true,
  "message": "Task permanently deleted",
  "timestamp": "2025-09-20T15:30:45Z"
}
```

### **List Trashed Tasks**
```http
GET /tasks/trashed
```
- Returns all soft-deleted tasks
- Useful for recovery operations
- Includes deletion timestamps

**Response**: 200 OK
```json
{
  "success": true,
  "message": "Trashed tasks retrieved successfully",
  "data": [
    {
      "id": 123,
      "title": "Deleted Task",
      "status": "pending",
      "deleted_at": "2025-09-20T14:30:45Z",
      "created_at": "2025-09-20T10:00:00Z"
    }
  ],
  "meta": {
    "count": 1,
    "note": "These are soft-deleted tasks that can be restored"
  }
}
```

## 🚫 Error Handling

### **TaskRestoreException (409 Conflict)**
Thrown when restore operations fail with specific reasons:

#### **Task Not Found**
```json
{
  "operation": "restore",
  "task_id": 123,
  "reason": "not_found",
  "suggestions": [
    "Task does not exist. Check the task ID.",
    "Use GET /tasks/trashed to see available trashed tasks."
  ]
}
```

#### **Task Already Active**
```json
{
  "operation": "restore", 
  "task_id": 123,
  "reason": "already_restored",
  "suggestions": [
    "Task is already active and does not need to be restored."
  ]
}
```

#### **Task Not In Trash**
```json
{
  "operation": "restore",
  "task_id": 123, 
  "reason": "not_in_trash",
  "suggestions": [
    "Task is not in trash. Check if the task exists and is deleted.",
    "Use GET /tasks/{id} to verify task status."
  ]
}
```

## 🗄️ Database Implementation

### **Migration**
```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled']);
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('assigned_to')->nullable();
    $table->timestamp('due_date')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->softDeletes(); // Adds deleted_at column
});
```

### **Model Configuration**
```php
class Task extends Model
{
    use SoftDeletes;
    
    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime', 
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime', // Important for soft deletes
    ];
}
```

## 📊 Logging System

### **New Log Actions**
- `deleted` - Task soft deleted
- `restored` - Task restored from trash
- `force_deleted` - Task permanently deleted

### **Log Structure**
```json
{
  "task_id": 123,
  "action": "restored",
  "old_data": [],
  "new_data": { "task": "data" },
  "user_id": null,
  "user_name": "System",
  "created_at": "2025-09-20T15:30:45Z"
}
```

## 🔧 Repository Methods

### **TaskRepositoryInterface**
```php
interface TaskRepositoryInterface
{
    // Existing methods...
    
    // New soft delete methods
    public function findTrashed(): Collection;
    public function findTrashedById(int $id): ?Task;
    public function findWithTrashed(): Collection;
    public function restore(int $id): bool;
    public function forceDelete(int $id): bool;
}
```

### **TaskRepository Implementation**
```php
// Find only trashed tasks
public function findTrashed(): Collection
{
    return Task::onlyTrashed()->get();
}

// Find specific trashed task
public function findTrashedById(int $id): ?Task
{
    return Task::onlyTrashed()->find($id);
}

// Find all tasks including trashed
public function findWithTrashed(): Collection  
{
    return Task::withTrashed()->get();
}

// Restore soft-deleted task
public function restore(int $id): bool
{
    $task = Task::withTrashed()->find($id);
    if (!$task || !$task->trashed()) {
        return false;
    }
    return $task->restore();
}

// Permanently delete task
public function forceDelete(int $id): bool
{
    $task = Task::withTrashed()->find($id);
    if (!$task) {
        return false;
    }
    return $task->forceDelete();
}
```

## 🎯 Query Behavior

### **Default Queries (Exclude Soft Deleted)**
```php
Task::all();                    // Only active tasks
Task::find(1);                  // Only if task is active
Task::where('status', 'pending')->get(); // Only active pending tasks
```

### **Including Soft Deleted**
```php
Task::withTrashed()->get();     // All tasks including soft deleted
Task::withTrashed()->find(1);   // Task even if soft deleted
```

### **Only Soft Deleted**
```php
Task::onlyTrashed()->get();     // Only soft deleted tasks
Task::onlyTrashed()->find(1);   // Task only if soft deleted
```

## 🧪 Testing

### **Feature Tests**
- Soft delete operations
- Restore functionality
- Force delete operations
- Trashed task listing
- Error scenarios
- Logging verification

### **Unit Tests**
- Repository method existence
- Model trait usage
- Database casting
- Fillable fields validation

### **Test Coverage**
- ✅ Soft delete task
- ✅ Restore task
- ✅ Force delete task
- ✅ List trashed tasks
- ✅ Error handling
- ✅ Logging verification
- ✅ Edge cases

## 🔒 Security Considerations

### **Access Control**
- All soft delete endpoints validate task IDs
- Proper exception handling prevents information disclosure
- Logging captures all operations for audit trails

### **Data Protection** 
- Soft deletes preserve data for recovery
- Force delete requires explicit action
- Audit trail maintained in MongoDB logs

## 📈 Performance Impact

### **Minimal Overhead**
- Soft delete adds single timestamp field
- Automatic query scoping by Laravel
- Indexes on `deleted_at` for performance

### **Database Growth**
- Soft deleted records remain in database
- Regular cleanup of force-deleted logs recommended
- Monitor storage usage over time

## 🚀 Usage Examples

### **Complete Workflow**
```bash
# Create task
POST /tasks
{
  "title": "Important Task",
  "description": "This task is important"
}

# Soft delete task
DELETE /tasks/123

# View trashed tasks
GET /tasks/trashed

# Restore task
POST /tasks/123/restore

# Force delete permanently  
DELETE /tasks/123/force
```

### **Error Recovery**
```bash
# Try to restore non-existent task
POST /tasks/99999/restore
# Returns 409 with helpful error message

# Try to restore active task
POST /tasks/123/restore  
# Returns 409 if task is already active
```

## 🎉 Benefits

### **Data Recovery**
- Accidentally deleted tasks can be recovered
- No permanent data loss from user errors
- Administrative oversight capabilities

### **Audit Compliance**
- Complete operation trail in logs
- Track who deleted/restored what and when
- Regulatory compliance support

### **User Experience**
- Undo functionality for deletions
- Clear error messages with suggestions
- Comprehensive task state management

---

**Implementation Status**: ✅ **COMPLETE**

The soft delete functionality is fully implemented with comprehensive error handling, logging, testing, and documentation. The system maintains backward compatibility while adding powerful data recovery capabilities.