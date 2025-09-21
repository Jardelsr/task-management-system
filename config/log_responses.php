<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Log Response Formatting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for formatting log responses
    | in the task management system API. These settings control how log data
    | is structured, formatted, and presented to API consumers.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Response Fields
    |--------------------------------------------------------------------------
    |
    | These are the fields that will be included by default in log responses.
    | Individual endpoints can override these settings as needed.
    |
    */
    'default_fields' => [
        '_id',
        'task_id',
        'action',
        'old_data',
        'new_data',
        'user_id',
        'user_name',
        'description',
        'created_at',
        'updated_at'
    ],

    /*
    |--------------------------------------------------------------------------
    | Required Fields
    |--------------------------------------------------------------------------
    |
    | These fields will always be included in responses regardless of
    | field filtering or customization options.
    |
    */
    'required_fields' => [
        '_id',
        'task_id',
        'action',
        'created_at'
    ],

    /*
    |--------------------------------------------------------------------------
    | Date Formatting Options
    |--------------------------------------------------------------------------
    |
    | Configure how dates are formatted in API responses.
    | Available formats: iso8601, timestamp, human, date_only, datetime
    |
    */
    'date_format' => [
        'default' => 'iso8601',
        'available_formats' => [
            'iso8601',      // 2024-01-15T10:30:00.000Z
            'timestamp',    // 1705316600
            'human',        // 2 hours ago
            'date_only',    // 2024-01-15
            'datetime',     // 2024-01-15 10:30:00
            'custom'        // Custom format string
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Metadata Configuration
    |--------------------------------------------------------------------------
    |
    | Control what metadata is included in log responses.
    |
    */
    'metadata' => [
        'include_by_default' => true,
        'include_technical_metadata' => false,
        'include_performance_metrics' => false,
        'include_change_summary' => true,
        'include_individual_metadata' => false, // For collections
        'include_collection_statistics' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Settings
    |--------------------------------------------------------------------------
    |
    | Default pagination settings for log listings.
    |
    */
    'pagination' => [
        'default_per_page' => 50,
        'max_per_page' => 1000,
        'min_per_page' => 1,
        'include_links' => true,
        'include_navigation_info' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Structure Options
    |--------------------------------------------------------------------------
    |
    | Configure the overall structure of log responses.
    |
    */
    'response_structure' => [
        'success_wrapper' => true,
        'timestamp_in_response' => true,
        'api_version_header' => true,
        'execution_time_tracking' => true,
        'request_id_tracking' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Action Display Names
    |--------------------------------------------------------------------------
    |
    | Human-readable names for log actions.
    |
    */
    'action_display_names' => [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'restored' => 'Restored',
        'force_deleted' => 'Permanently Deleted'
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Formatting Rules
    |--------------------------------------------------------------------------
    |
    | Rules for formatting different types of data in responses.
    |
    */
    'data_formatting' => [
        'null_handling' => 'exclude', // 'exclude', 'include_as_null', 'empty_object'
        'empty_arrays_as_null' => false,
        'numeric_strings_as_numbers' => true,
        'boolean_strings_as_boolean' => false
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance and Caching
    |--------------------------------------------------------------------------
    |
    | Settings related to response performance and caching.
    |
    */
    'performance' => [
        'enable_response_caching' => false,
        'cache_ttl' => 300, // 5 minutes
        'track_execution_time' => true,
        'include_query_statistics' => true,
        'optimize_for_mobile' => false
    ],

    /*
    |--------------------------------------------------------------------------
    | Security and Privacy
    |--------------------------------------------------------------------------
    |
    | Settings for handling sensitive data in log responses.
    |
    */
    'security' => [
        'mask_sensitive_fields' => true,
        'sensitive_field_patterns' => [
            'password',
            'token',
            'secret',
            'key',
            'credential'
        ],
        'mask_character' => '*',
        'mask_length' => 8,
        'expose_user_agents' => false,
        'expose_ip_addresses' => false
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling in Responses
    |--------------------------------------------------------------------------
    |
    | Configure how errors are handled within response formatting.
    |
    */
    'error_handling' => [
        'include_fallback_data' => true,
        'graceful_degradation' => true,
        'log_formatting_errors' => true,
        'default_error_message' => 'Unable to format response data'
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Field Processors
    |--------------------------------------------------------------------------
    |
    | Define custom processing for specific fields in responses.
    |
    */
    'custom_processors' => [
        'user_name' => [
            'default_value' => 'System',
            'null_replacement' => 'Unknown User'
        ],
        'task_id' => [
            'cast_to' => 'integer'
        ],
        'user_id' => [
            'cast_to' => 'integer',
            'null_allowed' => true
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | API Versioning Support
    |--------------------------------------------------------------------------
    |
    | Settings for supporting different API versions with different
    | response formats.
    |
    */
    'versioning' => [
        'version_1_0' => [
            'include_deprecated_fields' => false,
            'simplified_metadata' => false
        ],
        'version_2_0' => [
            'enhanced_metadata' => true,
            'include_change_analytics' => true
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Debugging and Development
    |--------------------------------------------------------------------------
    |
    | Settings useful during development and debugging.
    |
    */
    'debug' => [
        'include_debug_info' => env('APP_DEBUG', false),
        'show_query_info' => env('APP_DEBUG', false),
        'include_stack_trace' => false,
        'verbose_error_messages' => env('APP_DEBUG', false)
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization Support
    |--------------------------------------------------------------------------
    |
    | Settings for localizing response content.
    |
    */
    'localization' => [
        'localize_action_names' => false,
        'localize_error_messages' => false,
        'default_locale' => 'en',
        'date_locale_formatting' => false
    ]
];