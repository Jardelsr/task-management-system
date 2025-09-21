<?php

namespace App\Services;

use Illuminate\Support\Arr;
use App\Models\Task;

/**
 * Custom Validation Messages Service
 * 
 * This service provides centralized access to custom validation messages
 * and helps format them with dynamic content.
 */
class ValidationMessageService
{
    /**
     * Cache for loaded validation messages
     *
     * @var array|null
     */
    private static ?array $messages = null;

    /**
     * Get validation messages for task creation
     *
     * @return array
     */
    public static function getTaskCreationMessages(): array
    {
        $messages = self::getMessages('task_creation');
        
        // Replace dynamic placeholders
        if (isset($messages['status.in'])) {
            $messages['status.in'] = str_replace(
                ':values',
                implode(', ', Task::getAvailableStatuses()),
                $messages['status.in']
            );
        }

        return $messages;
    }

    /**
     * Get validation messages for task updates
     *
     * @return array
     */
    public static function getTaskUpdateMessages(): array
    {
        $messages = self::getMessages('task_update');
        
        // Replace dynamic placeholders
        if (isset($messages['status.in'])) {
            $messages['status.in'] = str_replace(
                ':values',
                implode(', ', Task::getAvailableStatuses()),
                $messages['status.in']
            );
        }

        return $messages;
    }

    /**
     * Get validation messages for log operations
     *
     * @return array
     */
    public static function getLogValidationMessages(): array
    {
        return self::getMessages('log_validation');
    }

    /**
     * Get validation messages for log export operations
     *
     * @return array
     */
    public static function getLogExportMessages(): array
    {
        return self::getMessages('log_export');
    }

    /**
     * Get validation messages for log cleanup operations
     *
     * @return array
     */
    public static function getLogCleanupMessages(): array
    {
        return self::getMessages('log_cleanup');
    }

    /**
     * Get business rule validation messages
     *
     * @return array
     */
    public static function getBusinessRuleMessages(): array
    {
        return self::getMessages('business_rules');
    }

    /**
     * Get filtering validation messages
     *
     * @return array
     */
    public static function getFilteringMessages(): array
    {
        $messages = self::getMessages('filtering');
        
        // Replace dynamic placeholders
        if (isset($messages['status.in'])) {
            $messages['status.in'] = str_replace(
                ':values',
                implode(', ', Task::getAvailableStatuses()),
                $messages['status.in']
            );
        }

        return $messages;
    }

    /**
     * Get common field validation messages
     *
     * @return array
     */
    public static function getCommonMessages(): array
    {
        return self::getMessages('common');
    }

    /**
     * Get context error messages
     *
     * @return array
     */
    public static function getContextMessages(): array
    {
        return self::getMessages('context');
    }

    /**
     * Get all validation messages combined
     *
     * @return array
     */
    public static function getAllMessages(): array
    {
        $allMessages = [];
        $categories = [
            'task_creation',
            'task_update',
            'log_validation',
            'log_export',
            'log_cleanup',
            'business_rules',
            'filtering',
            'common',
            'context'
        ];

        foreach ($categories as $category) {
            $allMessages = array_merge($allMessages, self::getMessages($category));
        }

        return $allMessages;
    }

    /**
     * Get validation message for a specific rule with dynamic content
     *
     * @param string $rule The validation rule (e.g., 'title.required')
     * @param array $replacements Key-value pairs for dynamic replacements
     * @return string|null
     */
    public static function getMessage(string $rule, array $replacements = []): ?string
    {
        // Search through all message categories
        $allMessages = self::loadMessages();
        
        foreach ($allMessages as $category => $messages) {
            if (isset($messages[$rule])) {
                $message = $messages[$rule];
                
                // Apply dynamic replacements
                foreach ($replacements as $key => $value) {
                    $message = str_replace(":{$key}", $value, $message);
                }
                
                return $message;
            }
        }

        return null;
    }

    /**
     * Format a business rule message with context
     *
     * @param string $rule The business rule key
     * @param array $context Context data for the message
     * @return string
     */
    public static function getBusinessMessage(string $rule, array $context = []): string
    {
        $messages = self::getMessages('business_rules');
        $message = $messages[$rule] ?? "Business rule violation: {$rule}";
        
        // Apply context replacements
        foreach ($context as $key => $value) {
            $message = str_replace(":{$key}", $value, $message);
        }
        
        return $message;
    }

    /**
     * Format a context error message
     *
     * @param string $key The context message key
     * @param array $context Context data for the message
     * @return string
     */
    public static function getContextMessage(string $key, array $context = []): string
    {
        $messages = self::getMessages('context');
        $message = $messages[$key] ?? "System error occurred";
        
        // Apply context replacements
        foreach ($context as $contextKey => $value) {
            $message = str_replace(":{$contextKey}", $value, $message);
        }
        
        return $message;
    }

    /**
     * Merge common messages with specific messages
     *
     * @param array $specificMessages
     * @return array
     */
    public static function mergeWithCommonMessages(array $specificMessages): array
    {
        $commonMessages = self::getCommonMessages();
        return array_merge($commonMessages, $specificMessages);
    }

    /**
     * Get date range validation messages
     *
     * @return array
     */
    public static function getDateRangeValidationMessages(): array
    {
        return [
            'start_date.required' => 'The start date is required for date range queries.',
            'start_date.date' => 'The start date must be a valid date.',
            'end_date.required' => 'The end date is required for date range queries.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after' => 'The end date must be after the start date.',
            'limit.integer' => 'The limit must be a valid number.',
            'limit.min' => 'The limit must be at least 1.',
            'limit.max' => 'The limit cannot exceed 1,000.'
        ];
    }

    /**
     * Load messages from configuration file
     *
     * @return array
     */
    private static function loadMessages(): array
    {
        if (self::$messages === null) {
            self::$messages = config('validation_messages', []);
        }

        return self::$messages;
    }

    /**
     * Get messages for a specific category
     *
     * @param string $category
     * @return array
     */
    private static function getMessages(string $category): array
    {
        $allMessages = self::loadMessages();
        return $allMessages[$category] ?? [];
    }

    /**
     * Clear message cache (useful for testing)
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$messages = null;
    }

    /**
     * Get localized validation messages (enhanced implementation)
     * 
     * @param string $locale
     * @param string $category
     * @return array
     */
    public static function getLocalizedMessages(string $locale = 'en', string $category = 'all'): array
    {
        // First try to load from Laravel's lang files
        $langMessages = self::loadLanguageMessages($locale);
        
        if (!empty($langMessages)) {
            if ($category === 'all') {
                return self::flattenMessages($langMessages);
            }
            
            return $langMessages[$category] ?? [];
        }
        
        // Fallback to default config messages
        return $category === 'all' ? self::getAllMessages() : self::getMessages($category);
    }

    /**
     * Load messages from Laravel language files
     *
     * @param string $locale
     * @return array
     */
    private static function loadLanguageMessages(string $locale): array
    {
        $langPath = resource_path("lang/{$locale}/validation_messages.php");
        
        if (file_exists($langPath)) {
            return require $langPath;
        }
        
        // Fallback to English if requested locale not found
        if ($locale !== 'en') {
            $englishPath = resource_path("lang/en/validation_messages.php");
            if (file_exists($englishPath)) {
                return require $englishPath;
            }
        }
        
        return [];
    }

    /**
     * Flatten nested message arrays into dot notation keys
     *
     * @param array $messages
     * @param string $prefix
     * @return array
     */
    private static function flattenMessages(array $messages, string $prefix = ''): array
    {
        $flattened = [];
        
        foreach ($messages as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $flattened = array_merge($flattened, self::flattenMessages($value, $newKey));
            } else {
                $flattened[$newKey] = $value;
            }
        }
        
        return $flattened;
    }

    /**
     * Get localized business rule message
     *
     * @param string $rule The business rule key
     * @param array $context Context data for the message
     * @param string $locale Language locale
     * @return string
     */
    public static function getLocalizedBusinessMessage(string $rule, array $context = [], string $locale = 'en'): string
    {
        $messages = self::getLocalizedMessages($locale, 'business_rules');
        $message = $messages[$rule] ?? "Business rule violation: {$rule}";
        
        // Apply context replacements
        foreach ($context as $key => $value) {
            $message = str_replace(":{$key}", $value, $message);
        }
        
        return $message;
    }

    /**
     * Get localized context message
     *
     * @param string $key The context message key
     * @param array $context Context data for the message
     * @param string $locale Language locale
     * @return string
     */
    public static function getLocalizedContextMessage(string $key, array $context = [], string $locale = 'en'): string
    {
        $messages = self::getLocalizedMessages($locale, 'context');
        $message = $messages[$key] ?? "System error occurred";
        
        // Apply context replacements
        foreach ($context as $contextKey => $value) {
            $message = str_replace(":{$contextKey}", $value, $message);
        }
        
        return $message;
    }

    /**
     * Get validation messages with current application locale
     *
     * @param string $category
     * @return array
     */
    public static function getMessagesForCurrentLocale(string $category = 'all'): array
    {
        $locale = app()->getLocale();
        return self::getLocalizedMessages($locale, $category);
    }
}