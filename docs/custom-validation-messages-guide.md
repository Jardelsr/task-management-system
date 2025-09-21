# Enhanced Custom Validation Messages System

This document describes the enhanced custom validation messages system implemented for the Task Management System.

## Overview

The custom validation messages system provides:
- **Centralized message management** through configuration files
- **Multi-language support** with English and Portuguese translations
- **Business rule validation** with custom logic beyond basic field validation
- **Dynamic message formatting** with context-aware placeholders
- **Consistent error handling** across the entire application

## Architecture

### Core Components

1. **ValidationMessageService** - Central service for message management
2. **BusinessRulesValidator** - Handles complex business logic validation
3. **Configuration Files** - Centralized message storage
4. **Language Files** - Localization support
5. **Request Classes** - Updated to use the new system

## Configuration

### Main Configuration File
**Location**: `config/validation_messages.php`

Contains categorized validation messages:
- `task_creation` - Messages for task creation validation
- `task_update` - Messages for task update validation
- `log_validation` - Messages for log operation validation
- `business_rules` - Messages for business logic violations
- `filtering` - Messages for API filtering validation
- `common` - Common validation messages
- `context` - Contextual error messages

### Language Files
**Locations**: 
- `resources/lang/en/validation_messages.php` (English)
- `resources/lang/pt/validation_messages.php` (Portuguese)

Support for localized validation messages.

## Usage Examples

### Basic Usage

```php
use App\Services\ValidationMessageService;

// Get task creation messages
$messages = ValidationMessageService::getTaskCreationMessages();

// Get localized messages
$messages = ValidationMessageService::getLocalizedMessages('pt', 'task_creation');

// Get specific message with context
$message = ValidationMessageService::getBusinessMessage(
    'status_transition.invalid',
    ['from' => 'pending', 'to' => 'completed']
);
```

### In Request Classes

```php
use App\Services\ValidationMessageService;

class CreateTaskRequest
{
    public static function getValidationMessages(): array
    {
        return ValidationMessageService::getTaskCreationMessages();
    }
}
```

### Business Rules Validation

```php
use App\Services\BusinessRulesValidator;

// Validate status transition
BusinessRulesValidator::validateStatusTransition(
    $currentStatus, 
    $newStatus, 
    $context
);

// Validate complete business rules
BusinessRulesValidator::validateCompleteTaskBusinessRules(
    $data, 
    $existingTask, 
    $context
);
```

## Message Categories

### Task Creation Messages
Used when creating new tasks:
- Field validation (title, description, status, etc.)
- Data type validation
- Format validation

**Example**:
```php
'title.required' => 'A task title is required and cannot be empty.'
```

### Task Update Messages
Used when updating existing tasks:
- Similar to creation but adapted for partial updates
- Status-specific validations
- Completion date requirements

**Example**:
```php
'status.in' => 'Invalid status provided. Valid options are: :values'
```

### Business Rules Messages
Used for complex business logic validation:
- Status transition validation
- Assignment rules
- Due date business logic
- Task deletion constraints

**Example**:
```php
'status_transition.invalid' => 'Invalid status transition from ":from" to ":to".'
```

### Context Messages
Used for general application errors:
- Resource not found errors
- Permission denied errors
- System errors

**Example**:
```php
'task_not_found' => 'The requested task (ID: :id) could not be found or may have been deleted.'
```

## Business Rules

### Status Transitions
The system enforces valid status transitions:

```
pending → in_progress, cancelled
in_progress → completed, pending, cancelled
completed → (no transitions allowed)
cancelled → pending
```

### Assignment Rules
- Prevents self-assignment (creator = assignee)
- Validates user existence
- Enforces manager approval for high-priority tasks

### Due Date Rules
- Cannot set past due dates for active tasks
- Overdue completion requires acknowledgment
- Completion date required when marking as completed

## Localization

### Supported Languages
- **English (en)** - Default language
- **Portuguese (pt)** - Full translation available

### Adding New Languages

1. Create language directory: `resources/lang/{locale}/`
2. Create `validation_messages.php` with translated messages
3. Follow the same structure as existing language files

**Example for Spanish**:
```php
// resources/lang/es/validation_messages.php
return [
    'task_creation' => [
        'title.required' => 'El título de la tarea es obligatorio.',
        // ... more translations
    ]
];
```

### Using Localization

```php
// Get messages for current application locale
$messages = ValidationMessageService::getMessagesForCurrentLocale('task_creation');

// Get messages for specific locale
$messages = ValidationMessageService::getLocalizedMessages('pt', 'business_rules');

// Get localized business message
$message = ValidationMessageService::getLocalizedBusinessMessage(
    'status_transition.invalid',
    ['from' => 'pendente', 'to' => 'concluída'],
    'pt'
);
```

## Dynamic Message Formatting

Messages support placeholder replacement for dynamic content:

### Available Placeholders
- `:values` - Replaced with valid values (e.g., status options)
- `:from`, `:to` - Used in status transitions
- `:id` - Used in resource identification
- `:attribute` - Field name being validated

### Example Usage
```php
// Message template
'status_transition.invalid' => 'Invalid transition from ":from" to ":to".'

// Usage
$message = ValidationMessageService::getBusinessMessage(
    'status_transition.invalid',
    ['from' => 'pending', 'to' => 'completed']
);
// Result: "Invalid transition from "pending" to "completed"."
```

## Integration with Controllers

### Task Controller Example
```php
use App\Services\ValidationMessageService;
use App\Services\BusinessRulesValidator;

class TaskController extends Controller
{
    public function store(Request $request)
    {
        // Basic field validation
        $validator = app('validator')->make(
            $request->all(),
            CreateTaskRequest::getValidationRules(),
            ValidationMessageService::getTaskCreationMessages()
        );

        if ($validator->fails()) {
            throw new TaskValidationException(
                $validator->errors()->toArray()
            );
        }

        // Business rules validation
        BusinessRulesValidator::validateTaskCreationWorkflow(
            $validator->validated()
        );

        // Proceed with task creation...
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        
        // Basic field validation
        $validator = app('validator')->make(
            $request->all(),
            UpdateTaskRequest::getValidationRules(),
            ValidationMessageService::getTaskUpdateMessages()
        );

        if ($validator->fails()) {
            throw new TaskValidationException(
                $validator->errors()->toArray()
            );
        }

        // Business rules validation
        BusinessRulesValidator::validateCompleteTaskBusinessRules(
            $validator->validated(),
            $task
        );

        // Proceed with task update...
    }
}
```

## Error Response Format

Validation errors are returned in a consistent format:

```json
{
  "success": false,
  "error": "Validation failed",
  "message": "Task validation failed",
  "timestamp": "2025-09-21T15:30:00.000000Z",
  "code": "VALIDATION_FAILED",
  "details": {
    "errors": {
      "title": ["A task title is required and cannot be empty."],
      "status": ["Invalid status transition from \"completed\" to \"pending\"."]
    }
  }
}
```

## Testing

### Unit Tests
Comprehensive unit tests are provided in `tests/Unit/CustomValidationMessagesTest.php`:

```bash
# Run validation message tests
./vendor/bin/phpunit tests/Unit/CustomValidationMessagesTest.php

# Run business rules tests
./vendor/bin/phpunit tests/Unit/CustomValidationMessagesTest.php --filter BusinessRulesValidatorTest
```

### Test Coverage
Tests cover:
- Message retrieval and formatting
- Localization functionality
- Business rules validation
- Dynamic placeholder replacement
- Error handling scenarios

## Performance Considerations

### Message Caching
- Messages are cached in memory after first load
- Use `ValidationMessageService::clearCache()` to reset cache
- Language files are loaded on-demand

### Optimization Tips
- Use specific message categories instead of `getAllMessages()`
- Cache frequently used messages at application level
- Consider lazy loading for large message sets

## Migration Guide

### From Old System
If migrating from inline validation messages:

1. **Identify existing messages** in request classes
2. **Move messages** to appropriate categories in config
3. **Update request classes** to use `ValidationMessageService`
4. **Test thoroughly** to ensure no message regression

### Example Migration
**Before**:
```php
class CreateTaskRequest
{
    public function messages()
    {
        return [
            'title.required' => 'Title is required',
            // ... other messages
        ];
    }
}
```

**After**:
```php
class CreateTaskRequest
{
    public function messages()
    {
        return ValidationMessageService::getTaskCreationMessages();
    }
}
```

## Best Practices

### Message Writing Guidelines
1. **Be specific and actionable** - Tell users exactly what's wrong and how to fix it
2. **Use consistent tone** - Professional but friendly
3. **Include context** - Mention field names and valid values
4. **Avoid technical jargon** - Use language that end users understand
5. **Support localization** - Write messages that translate well

### Code Organization
1. **Group related messages** by functionality
2. **Use consistent naming** for message keys
3. **Document placeholder usage** in complex messages
4. **Keep business rules separate** from field validation
5. **Test all message paths** in your application

### Performance Guidelines
1. **Cache messages** when possible
2. **Load only needed categories** for specific operations
3. **Use lazy loading** for large message sets
4. **Monitor memory usage** with extensive localization

## Future Enhancements

### Planned Features
1. **Message versioning** - Track changes to validation messages
2. **Dynamic message loading** - Load messages from database
3. **A/B testing support** - Test different message variations
4. **Message analytics** - Track which messages users see most
5. **Additional languages** - Expand localization support

### Extension Points
The system is designed to be extensible:
- Add new message categories in config
- Create new language files
- Extend BusinessRulesValidator with custom rules
- Override message loading logic in ValidationMessageService

## Troubleshooting

### Common Issues

1. **Messages not loading**
   - Check file permissions
   - Verify config file syntax
   - Clear Laravel config cache

2. **Localization not working**
   - Ensure language files exist
   - Check Laravel locale configuration
   - Verify file structure matches

3. **Business rules not enforcing**
   - Check controller integration
   - Verify exception handling
   - Review rule logic in BusinessRulesValidator

### Debug Tools
```php
// Check if messages are loading
$messages = ValidationMessageService::getAllMessages();
var_dump(count($messages));

// Test localization
$messages = ValidationMessageService::getLocalizedMessages('pt');
var_dump($messages);

// Verify business rules
$transitions = BusinessRulesValidator::getValidStatusTransitions();
var_dump($transitions);
```

---

This enhanced validation system provides a robust foundation for handling all validation requirements in the Task Management System while maintaining flexibility for future enhancements and localization needs.