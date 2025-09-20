<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * Trait for consistent success response formatting
 */
trait SuccessResponseTrait
{
    use ApiHeadersTrait;
    /**
     * Create a standardized success response
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @param array $meta
     * @return JsonResponse
     */
    protected function successResponse(
        $data = null,
        ?string $message = null,
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'timestamp' => Carbon::now()->toISOString()
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        $jsonResponse = response()->json($response, $statusCode);

        // Add consistent API headers
        return $this->addApiHeaders($jsonResponse);
    }

    /**
     * Create a paginated success response
     *
     * @param mixed $data
     * @param array $pagination
     * @param string|null $message
     * @param array $additionalMeta
     * @return JsonResponse
     */
    protected function paginatedResponse(
        $data,
        array $pagination,
        ?string $message = null,
        array $additionalMeta = []
    ): JsonResponse {
        $request = request();
        
        // Build comprehensive pagination metadata
        $paginationMeta = [
            'current_page' => $pagination['current_page'] ?? 1,
            'per_page' => $pagination['per_page'] ?? 50,
            'total' => $pagination['total'] ?? 0,
            'total_pages' => $pagination['total_pages'] ?? 
                (isset($pagination['total'], $pagination['per_page']) 
                    ? ceil($pagination['total'] / $pagination['per_page']) 
                    : 0),
            'has_next_page' => $pagination['has_next_page'] ?? false,
            'has_previous_page' => $pagination['has_previous_page'] ?? false,
            'next_page' => $pagination['next_page'] ?? null,
            'previous_page' => $pagination['previous_page'] ?? null
        ];
        
        // Build enhanced metadata
        $meta = $this->buildRequestMetadata($request, [
            'data_type' => 'collection',
            'data_count' => is_array($data) ? count($data) : 0
        ]);

        // Merge with additional metadata and pagination
        $finalMeta = array_merge($meta, $additionalMeta, ['pagination' => $paginationMeta]);

        return $this->successResponse($data, $message, 200, $finalMeta);
    }

    /**
     * Create a success response for resource creation
     *
     * @param mixed $data
     * @param string|null $message
     * @return JsonResponse
     */
    protected function createdResponse(
        $data = null,
        ?string $message = null
    ): JsonResponse {
        return $this->successResponse(
            $data,
            $message ?? 'Resource created successfully',
            201
        );
    }

    /**
     * Create a success response for resource updates
     *
     * @param mixed $data
     * @param string|null $message
     * @return JsonResponse
     */
    protected function updatedResponse(
        $data = null,
        ?string $message = null
    ): JsonResponse {
        return $this->successResponse(
            $data,
            $message ?? 'Resource updated successfully',
            200
        );
    }

    /**
     * Create a success response for resource deletion
     *
     * @param string|null $message
     * @param array $meta
     * @return JsonResponse
     */
    protected function deletedResponse(
        ?string $message = null,
        array $meta = []
    ): JsonResponse {
        return $this->successResponse(
            null,
            $message ?? 'Resource deleted successfully',
            204,
            $meta
        );
    }

    /**
     * Create a no content response
     *
     * @return JsonResponse
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Create a success response with statistics/counts
     *
     * @param array $stats
     * @param string|null $message
     * @return JsonResponse
     */
    protected function statsResponse(
        array $stats,
        ?string $message = null
    ): JsonResponse {
        return $this->successResponse(
            $stats,
            $message ?? 'Statistics retrieved successfully',
            200,
            ['type' => 'statistics']
        );
    }

    /**
     * Create a success response for bulk operations
     *
     * @param array $results
     * @param string|null $message
     * @return JsonResponse
     */
    protected function bulkOperationResponse(
        array $results,
        ?string $message = null
    ): JsonResponse {
        $meta = [
            'operation_type' => 'bulk',
            'total_processed' => $results['total_processed'] ?? 0,
            'successful' => $results['successful'] ?? 0,
            'failed' => $results['failed'] ?? 0
        ];

        if (isset($results['errors']) && !empty($results['errors'])) {
            $meta['errors'] = $results['errors'];
        }

        return $this->successResponse(
            $results['data'] ?? null,
            $message ?? 'Bulk operation completed',
            200,
            $meta
        );
    }

    /**
     * Create a success response for task-specific operations
     *
     * @param mixed $data
     * @param string $action
     * @param string|null $message
     * @return JsonResponse
     */
    protected function taskOperationResponse(
        $data,
        string $action,
        ?string $message = null
    ): JsonResponse {
        $actionMessages = [
            'created' => 'Task created successfully',
            'updated' => 'Task updated successfully',
            'deleted' => 'Task deleted successfully',
            'restored' => 'Task restored successfully',
            'completed' => 'Task marked as completed',
            'started' => 'Task started',
            'assigned' => 'Task assigned successfully'
        ];

        $defaultMessage = $actionMessages[$action] ?? 'Task operation completed successfully';

        return $this->successResponse(
            $data,
            $message ?? $defaultMessage,
            in_array($action, ['created']) ? 201 : 200,
            ['operation' => $action]
        );
    }

    /**
     * Create a success response for log operations
     *
     * @param mixed $data
     * @param string|null $message
     * @param array $filters
     * @return JsonResponse
     */
    protected function logResponse(
        $data,
        ?string $message = null,
        array $filters = []
    ): JsonResponse {
        $meta = ['type' => 'logs'];
        
        if (!empty($filters)) {
            $meta['applied_filters'] = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
        }

        return $this->successResponse(
            $data,
            $message ?? 'Logs retrieved successfully',
            200,
            $meta
        );
    }

    /**
     * Add request metadata to response
     *
     * @param \Illuminate\Http\Request $request
     * @param array $additionalMeta
     * @return array
     */
    protected function buildRequestMetadata($request, array $additionalMeta = []): array
    {
        $meta = [
            'request_id' => $request->header('X-Request-ID', uniqid('req_', true)),
            'api_version' => config('api.version', '1.0'),
            'timestamp' => Carbon::now()->toISOString(),
        ];

        if (config('api.responses.include_execution_time', true)) {
            $meta['execution_time'] = $this->getExecutionTime() . 'ms';
        }

        if ($request->hasHeader('X-User-ID')) {
            $meta['user_id'] = $request->header('X-User-ID');
        }

        return array_merge($meta, $additionalMeta);
    }

    /**
     * Enhanced success response with metadata and performance tracking
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @param array $meta
     * @param \Illuminate\Http\Request|null $request
     * @return JsonResponse
     */
    protected function enhancedSuccessResponse(
        $data = null,
        ?string $message = null,
        int $statusCode = 200,
        array $meta = [],
        $request = null
    ): JsonResponse {
        $request = $request ?? request();
        
        // Build comprehensive metadata
        $enhancedMeta = $this->buildRequestMetadata($request, $meta);
        
        // Add data type information
        if ($data !== null) {
            $enhancedMeta['data_type'] = is_array($data) ? 'collection' : 'object';
            if (is_array($data)) {
                $enhancedMeta['data_count'] = count($data);
            }
        }

        return $this->successResponse($data, $message, $statusCode, $enhancedMeta);
    }

    /**
     * Create response with resource transformation metadata
     *
     * @param mixed $data
     * @param string $resourceType
     * @param string|null $message
     * @param array $transformationMeta
     * @return JsonResponse
     */
    protected function resourceResponse(
        $data,
        string $resourceType,
        ?string $message = null,
        array $transformationMeta = []
    ): JsonResponse {
        $meta = [
            'resource_type' => $resourceType,
            'timestamp' => Carbon::now()->toISOString()
        ];

        if (!empty($transformationMeta)) {
            $meta['transformation'] = $transformationMeta;
        }

        return $this->successResponse($data, $message, 200, $meta);
    }

    /**
     * Create response with validation metadata
     *
     * @param mixed $data
     * @param string|null $message
     * @param array $validationMeta
     * @return JsonResponse
     */
    protected function validatedResponse(
        $data,
        ?string $message = null,
        array $validationMeta = []
    ): JsonResponse {
        $meta = ['validation' => $validationMeta];
        
        return $this->successResponse($data, $message, 200, $meta);
    }
}