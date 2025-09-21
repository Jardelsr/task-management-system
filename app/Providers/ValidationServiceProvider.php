<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Factory as ValidationFactory;

/**
 * Validation Service Provider
 * 
 * This provider ensures that validation is properly configured in Lumen
 * and custom validation messages are loaded.
 */
class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register validation factory if not already registered
        if (!$this->app->bound('validator')) {
            $this->app->bind('validator', function ($app) {
                return new ValidationFactory($app['translator'], $app);
            });
        }
    }

    /**
     * Boot the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Ensure validation messages config is loaded
        $this->app->configure('validation_messages');
        
        // Extend validator with custom rules if needed
        $this->registerCustomValidationRules();
    }

    /**
     * Register custom validation rules
     *
     * @return void
     */
    protected function registerCustomValidationRules()
    {
        $validator = $this->app->make('validator');
        
        // Example custom rule: validate_task_status
        $validator->extend('task_status', function ($attribute, $value, $parameters, $validator) {
            return in_array($value, \App\Models\Task::getAvailableStatuses());
        });
        
        // Custom rule message
        $validator->replacer('task_status', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, 'The :attribute must be a valid task status.');
        });

        // Example custom rule: validate_task_priority
        $validator->extend('task_priority', function ($attribute, $value, $parameters, $validator) {
            return in_array($value, ['low', 'medium', 'high']);
        });
        
        // Custom rule message
        $validator->replacer('task_priority', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, 'The :attribute must be low, medium, or high.');
        });

        // Example custom rule: validate_future_date_limit
        $validator->extend('future_date_limit', function ($attribute, $value, $parameters, $validator) {
            $maxYears = $parameters[0] ?? 10;
            $date = \Carbon\Carbon::parse($value);
            $maxDate = now()->addYears($maxYears);
            
            return $date->lte($maxDate);
        });
        
        // Custom rule message
        $validator->replacer('future_date_limit', function ($message, $attribute, $rule, $parameters) {
            $maxYears = $parameters[0] ?? 10;
            return str_replace([':attribute', ':years'], [$attribute, $maxYears], 
                'The :attribute cannot be more than :years years in the future.');
        });
    }
}