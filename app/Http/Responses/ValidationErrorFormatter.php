<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Enhanced Validation Error Response Formatter for Lumen
 * 
 * Provides consistent, structured validation error responses
 * with detailed field-level error information and metadata
 * 
 * @deprecated Use ErrorResponseFormatter::validationError() instead
 */
class ValidationErrorFormatter
{
    /**
     * Format validation errors into a consistent response structure
     *
     * @param array $errors Array of validation errors
     * @param string $message Main error message
     * @param array $context Additional context information
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    public static function format(
        array $errors, 
        string $message = 'The given data was invalid',
        array $context = [],
        int $statusCode = 422
    ): JsonResponse {
        // Use the new centralized error formatter
        return ErrorResponseFormatter::validationError($errors, $message, $context);
    }

    /**
     * Format validation errors from a ValidationException
     *
     * @param ValidationException $exception
     * @param array $context Additional context
     * @return JsonResponse
     */
    public static function fromValidationException(
        ValidationException $exception, 
        array $context = []
    ): JsonResponse {
        return ErrorResponseFormatter::validationError(
            $exception->errors(),
            $exception->getMessage() ?: 'The given data was invalid',
            $context
        );
    }

    /**
     * Create a validation error response for missing required fields
     *
     * @param array $missingFields Array of missing field names
     * @param string $message Custom message
     * @return JsonResponse
     */
    public static function missingFields(
        array $missingFields, 
        string $message = 'Required fields are missing'
    ): JsonResponse {
        $errors = [];
        foreach ($missingFields as $field) {
            $errors[$field] = ["The {$field} field is required."];
        }

        return ErrorResponseFormatter::validationError($errors, $message, [
            'missing_fields' => $missingFields,
            'suggestion' => 'Please provide all required fields in your request.'
        ]);
    }

    /**
     * Create a validation error response for invalid field formats
     *
     * @param array $invalidFields Array of field names with their format requirements
     * @param string $message Custom message
     * @return JsonResponse
     */
    public static function invalidFormats(
        array $invalidFields, 
        string $message = 'Some fields have invalid formats'
    ): JsonResponse {
        $errors = [];
        foreach ($invalidFields as $field => $requirement) {
            $errors[$field] = ["The {$field} field must be a valid {$requirement}."];
        }

        return ErrorResponseFormatter::validationError($errors, $message, [
            'invalid_formats' => $invalidFields,
            'suggestion' => 'Please check the format requirements for each field.'
        ]);
    }
}