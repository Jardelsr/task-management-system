<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Exceptions\TaskValidationException;
use App\Exceptions\TaskOperationException;

/**
 * Trait for comprehensive input validation and error handling
 */
trait InputValidationTrait
{
    use SecurityErrorHandlingTrait;

    /**
     * Comprehensive input validation with sanitization
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param bool $sanitize
     * @return array
     * @throws TaskValidationException
     */
    protected function validateAndSanitizeInput(
        array $data,
        array $rules,
        array $messages = [],
        bool $sanitize = true
    ): array {
        // First, validate request size and structure
        $this->validateRequestSize($data);

        // Sanitize input if requested
        if ($sanitize) {
            $data = $this->sanitizeInput($data);
        }

        // Security validation
        $this->validateRequestSecurity($data);

        // Perform standard validation
        $validator = app('validator')->make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new TaskValidationException(
                $validator->errors()->toArray(),
                'input_validation',
                'Validation failed for provided data'
            );
        }

        return $validator->validated();
    }

    /**
     * Validate specific data types with advanced checks
     *
     * @param mixed $value
     * @param string $type
     * @param array $options
     * @return mixed
     * @throws TaskValidationException
     */
    protected function validateDataType($value, string $type, array $options = [])
    {
        switch ($type) {
            case 'email':
                return $this->validateEmail($value, $options);
            
            case 'url':
                return $this->validateUrl($value, $options);
            
            case 'date':
                return $this->validateDate($value, $options);
            
            case 'json':
                return $this->validateJson($value, $options);
            
            case 'integer':
                return $this->validateInteger($value, $options);
            
            case 'string':
                return $this->validateString($value, $options);
            
            case 'array':
                return $this->validateArray($value, $options);
            
            case 'boolean':
                return $this->validateBoolean($value);
            
            case 'numeric':
                return $this->validateNumeric($value, $options);
            
            default:
                throw new TaskValidationException(
                    ['type' => ["Unknown validation type: {$type}"]],
                    'validation_config'
                );
        }
    }

    /**
     * Validate email with advanced checks
     *
     * @param mixed $value
     * @param array $options
     * @return string
     * @throws TaskValidationException
     */
    private function validateEmail($value, array $options = []): string
    {
        if (!is_string($value)) {
            throw new TaskValidationException(
                ['email' => ['Email must be a string']],
                'type_validation'
            );
        }

        // Basic email validation
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new TaskValidationException(
                ['email' => ['Invalid email format']],
                'email_validation'
            );
        }

        // Check domain if specified
        if (isset($options['allowed_domains'])) {
            $domain = substr(strrchr($value, "@"), 1);
            if (!in_array($domain, $options['allowed_domains'])) {
                throw new TaskValidationException(
                    ['email' => ['Email domain not allowed']],
                    'domain_validation'
                );
            }
        }

        // Check for disposable email domains
        if ($options['block_disposable'] ?? false) {
            if ($this->isDisposableEmail($value)) {
                throw new TaskValidationException(
                    ['email' => ['Disposable email addresses are not allowed']],
                    'disposable_email'
                );
            }
        }

        return strtolower($value);
    }

    /**
     * Validate URL with protocol and domain checks
     *
     * @param mixed $value
     * @param array $options
     * @return string
     * @throws TaskValidationException
     */
    private function validateUrl($value, array $options = []): string
    {
        if (!is_string($value)) {
            throw new TaskValidationException(
                ['url' => ['URL must be a string']],
                'type_validation'
            );
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new TaskValidationException(
                ['url' => ['Invalid URL format']],
                'url_validation'
            );
        }

        // Check allowed schemes
        $allowedSchemes = $options['schemes'] ?? ['http', 'https'];
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (!in_array($scheme, $allowedSchemes)) {
            throw new TaskValidationException(
                ['url' => ['URL scheme not allowed']],
                'scheme_validation'
            );
        }

        // Check domain whitelist
        if (isset($options['allowed_domains'])) {
            $host = parse_url($value, PHP_URL_HOST);
            if (!in_array($host, $options['allowed_domains'])) {
                throw new TaskValidationException(
                    ['url' => ['URL domain not allowed']],
                    'domain_validation'
                );
            }
        }

        return $value;
    }

    /**
     * Validate date with format and range checks
     *
     * @param mixed $value
     * @param array $options
     * @return string
     * @throws TaskValidationException
     */
    private function validateDate($value, array $options = []): string
    {
        if (!is_string($value)) {
            throw new TaskValidationException(
                ['date' => ['Date must be a string']],
                'type_validation'
            );
        }

        $format = $options['format'] ?? 'Y-m-d H:i:s';
        
        try {
            $date = \DateTime::createFromFormat($format, $value);
            if (!$date || $date->format($format) !== $value) {
                throw new TaskValidationException(
                    ['date' => ["Date must be in format: {$format}"]],
                    'date_format'
                );
            }
        } catch (\Exception $e) {
            throw new TaskValidationException(
                ['date' => ['Invalid date format']],
                'date_parsing'
            );
        }

        // Check date ranges
        if (isset($options['min_date'])) {
            $minDate = new \DateTime($options['min_date']);
            if ($date < $minDate) {
                throw new TaskValidationException(
                    ['date' => ["Date must be after {$options['min_date']}"]],
                    'date_range'
                );
            }
        }

        if (isset($options['max_date'])) {
            $maxDate = new \DateTime($options['max_date']);
            if ($date > $maxDate) {
                throw new TaskValidationException(
                    ['date' => ["Date must be before {$options['max_date']}"]],
                    'date_range'
                );
            }
        }

        return $value;
    }

    /**
     * Validate JSON string
     *
     * @param mixed $value
     * @param array $options
     * @return array|string
     * @throws TaskValidationException
     */
    private function validateJson($value, array $options = [])
    {
        if (!is_string($value)) {
            throw new TaskValidationException(
                ['json' => ['JSON must be a string']],
                'type_validation'
            );
        }

        $decoded = json_decode($value, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new TaskValidationException(
                ['json' => ['Invalid JSON format: ' . json_last_error_msg()]],
                'json_validation'
            );
        }

        // Check maximum depth
        $maxDepth = $options['max_depth'] ?? 10;
        if (is_array($decoded)) {
            $depth = $this->getArrayDepth($decoded);
            if ($depth > $maxDepth) {
                throw new TaskValidationException(
                    ['json' => ["JSON structure too deep (max: {$maxDepth} levels)"]],
                    'json_depth'
                );
            }
        }

        return $options['return_decoded'] ?? false ? $decoded : $value;
    }

    /**
     * Validate integer with range checks
     *
     * @param mixed $value
     * @param array $options
     * @return int
     * @throws TaskValidationException
     */
    private function validateInteger($value, array $options = []): int
    {
        if (!is_numeric($value) || (int)$value != $value) {
            throw new TaskValidationException(
                ['integer' => ['Value must be an integer']],
                'type_validation'
            );
        }

        $intValue = (int)$value;

        // Check minimum value
        if (isset($options['min']) && $intValue < $options['min']) {
            throw new TaskValidationException(
                ['integer' => ["Value must be at least {$options['min']}"]],
                'range_validation'
            );
        }

        // Check maximum value
        if (isset($options['max']) && $intValue > $options['max']) {
            throw new TaskValidationException(
                ['integer' => ["Value must be no more than {$options['max']}"]],
                'range_validation'
            );
        }

        return $intValue;
    }

    /**
     * Validate string with length and pattern checks
     *
     * @param mixed $value
     * @param array $options
     * @return string
     * @throws TaskValidationException
     */
    private function validateString($value, array $options = []): string
    {
        if (!is_string($value)) {
            throw new TaskValidationException(
                ['string' => ['Value must be a string']],
                'type_validation'
            );
        }

        $length = mb_strlen($value);

        // Check minimum length
        if (isset($options['min_length']) && $length < $options['min_length']) {
            throw new TaskValidationException(
                ['string' => ["String must be at least {$options['min_length']} characters"]],
                'length_validation'
            );
        }

        // Check maximum length
        if (isset($options['max_length']) && $length > $options['max_length']) {
            throw new TaskValidationException(
                ['string' => ["String must be no more than {$options['max_length']} characters"]],
                'length_validation'
            );
        }

        // Check pattern
        if (isset($options['pattern'])) {
            if (!preg_match($options['pattern'], $value)) {
                $patternName = $options['pattern_name'] ?? 'required pattern';
                throw new TaskValidationException(
                    ['string' => ["String must match {$patternName}"]],
                    'pattern_validation'
                );
            }
        }

        // Check forbidden characters
        if (isset($options['forbidden_chars'])) {
            foreach ($options['forbidden_chars'] as $char) {
                if (strpos($value, $char) !== false) {
                    throw new TaskValidationException(
                        ['string' => ["String contains forbidden character: {$char}"]],
                        'character_validation'
                    );
                }
            }
        }

        return $value;
    }

    /**
     * Validate array with size and structure checks
     *
     * @param mixed $value
     * @param array $options
     * @return array
     * @throws TaskValidationException
     */
    private function validateArray($value, array $options = []): array
    {
        if (!is_array($value)) {
            throw new TaskValidationException(
                ['array' => ['Value must be an array']],
                'type_validation'
            );
        }

        $count = count($value);

        // Check minimum size
        if (isset($options['min_items']) && $count < $options['min_items']) {
            throw new TaskValidationException(
                ['array' => ["Array must have at least {$options['min_items']} items"]],
                'size_validation'
            );
        }

        // Check maximum size
        if (isset($options['max_items']) && $count > $options['max_items']) {
            throw new TaskValidationException(
                ['array' => ["Array must have no more than {$options['max_items']} items"]],
                'size_validation'
            );
        }

        // Validate array items if specified
        if (isset($options['item_type'])) {
            foreach ($value as $index => $item) {
                try {
                    $value[$index] = $this->validateDataType(
                        $item, 
                        $options['item_type'], 
                        $options['item_options'] ?? []
                    );
                } catch (TaskValidationException $e) {
                    throw new TaskValidationException(
                        ["array.{$index}" => $e->getValidationErrors()],
                        'array_item_validation'
                    );
                }
            }
        }

        return $value;
    }

    /**
     * Validate boolean value
     *
     * @param mixed $value
     * @return bool
     * @throws TaskValidationException
     */
    private function validateBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false', 'no', 'off'], true)) {
            return false;
        }

        throw new TaskValidationException(
            ['boolean' => ['Value must be a valid boolean']],
            'type_validation'
        );
    }

    /**
     * Validate numeric value with precision checks
     *
     * @param mixed $value
     * @param array $options
     * @return float
     * @throws TaskValidationException
     */
    private function validateNumeric($value, array $options = []): float
    {
        if (!is_numeric($value)) {
            throw new TaskValidationException(
                ['numeric' => ['Value must be numeric']],
                'type_validation'
            );
        }

        $numValue = (float)$value;

        // Check minimum value
        if (isset($options['min']) && $numValue < $options['min']) {
            throw new TaskValidationException(
                ['numeric' => ["Value must be at least {$options['min']}"]],
                'range_validation'
            );
        }

        // Check maximum value
        if (isset($options['max']) && $numValue > $options['max']) {
            throw new TaskValidationException(
                ['numeric' => ["Value must be no more than {$options['max']}"]],
                'range_validation'
            );
        }

        // Check decimal places
        if (isset($options['decimal_places'])) {
            $decimals = strlen(substr(strrchr($value, "."), 1));
            if ($decimals > $options['decimal_places']) {
                throw new TaskValidationException(
                    ['numeric' => ["Value can have at most {$options['decimal_places']} decimal places"]],
                    'precision_validation'
                );
            }
        }

        return $numValue;
    }

    /**
     * Check if email is from a disposable email service
     *
     * @param string $email
     * @return bool
     */
    private function isDisposableEmail(string $email): bool
    {
        $disposableDomains = [
            '10minutemail.com',
            'tempmail.org',
            'guerrillamail.com',
            'mailinator.com',
            'throwaway.email',
            // Add more as needed
        ];

        $domain = substr(strrchr($email, "@"), 1);
        return in_array($domain, $disposableDomains);
    }

    /**
     * Batch validate multiple fields
     *
     * @param array $data
     * @param array $validationRules
     * @return array
     * @throws TaskValidationException
     */
    protected function batchValidate(array $data, array $validationRules): array
    {
        $validated = [];
        $errors = [];

        foreach ($validationRules as $field => $rule) {
            if (!array_key_exists($field, $data)) {
                if ($rule['required'] ?? false) {
                    $errors[$field] = ['Field is required'];
                }
                continue;
            }

            try {
                $validated[$field] = $this->validateDataType(
                    $data[$field],
                    $rule['type'],
                    $rule['options'] ?? []
                );
            } catch (TaskValidationException $e) {
                $errors[$field] = array_values($e->getValidationErrors())[0] ?? ['Validation failed'];
            }
        }

        if (!empty($errors)) {
            throw new TaskValidationException($errors, 'batch_validation');
        }

        return $validated;
    }
}