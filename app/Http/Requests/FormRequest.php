<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Validation\Validator;
use App\Exceptions\TaskValidationException;

/**
 * Base FormRequest class for Lumen
 * 
 * Lumen doesn't have built-in FormRequest like Laravel,
 * so we create a base class to handle validation consistently
 */
abstract class FormRequest
{
    protected Request $request;
    protected array $validatedData = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Get custom error messages for validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Validate the request data
     *
     * @return array
     * @throws TaskValidationException
     */
    public function validate(): array
    {
        $validator = app('validator')->make(
            $this->request->all(),
            $this->rules(),
            $this->messages(),
            $this->attributes()
        );

        if ($validator->fails()) {
            throw new TaskValidationException($validator->errors()->toArray());
        }

        $this->validatedData = $validator->validated();
        return $this->validatedData;
    }

    /**
     * Get validated data
     *
     * @return array
     */
    public function validated(): array
    {
        if (empty($this->validatedData)) {
            $this->validate();
        }
        
        return $this->validatedData;
    }

    /**
     * Get specific validated field
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getValidated(string $key, $default = null)
    {
        return $this->validated()[$key] ?? $default;
    }

    /**
     * Static method to create and validate request
     *
     * @param Request $request
     * @return static
     * @throws TaskValidationException
     */
    public static function createFromRequest(Request $request): static
    {
        $instance = new static($request);
        $instance->validate();
        return $instance;
    }

    /**
     * Get all request data
     *
     * @return array
     */
    public function all(): array
    {
        return $this->request->all();
    }

    /**
     * Get specific request field
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->request->get($key, $default);
    }

    /**
     * Check if request has field
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->request->has($key);
    }
}