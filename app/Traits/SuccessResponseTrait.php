<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * Trait for consistent success response formatting
 */
trait SuccessResponseTrait
{
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

        return response()->json($response, $statusCode);
    }

    /**
     * Create a paginated success response
     *
     * @param mixed $data
     * @param array $pagination
     * @param string|null $message
     * @return JsonResponse
     */
    protected function paginatedResponse(
        $data,
        array $pagination,
        ?string $message = null
    ): JsonResponse {
        $meta = [
            'pagination' => [
                'current_page' => $pagination['current_page'] ?? 1,
                'per_page' => $pagination['per_page'] ?? 50,
                'total' => $pagination['total'] ?? 0,
                'total_pages' => isset($pagination['total'], $pagination['per_page']) 
                    ? ceil($pagination['total'] / $pagination['per_page']) 
                    : 0,
                'from' => $pagination['from'] ?? null,
                'to' => $pagination['to'] ?? null,
                'has_more' => $pagination['has_more'] ?? false
            ]
        ];

        return $this->successResponse($data, $message, 200, $meta);
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
}