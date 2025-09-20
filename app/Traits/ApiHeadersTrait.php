<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * Trait for adding consistent API headers to responses
 */
trait ApiHeadersTrait
{
    /**
     * Get execution time since request start
     *
     * @return float
     */
    protected function getExecutionTime(): float
    {
        return round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2);
    }

    /**
     * Add consistent API headers to response
     *
     * @param JsonResponse $response
     * @return JsonResponse
     */
    protected function addApiHeaders(JsonResponse $response): JsonResponse
    {
        $request = request();
        
        return $response->withHeaders([
            'X-API-Version' => config('api.version', '1.0'),
            'X-Request-ID' => $request->header('X-Request-ID', uniqid('req_', true)),
            'X-Execution-Time' => $this->getExecutionTime() . 'ms',
            'X-Timestamp' => Carbon::now()->toISOString(),
            'Cache-Control' => 'no-cache, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block'
        ]);
    }
}