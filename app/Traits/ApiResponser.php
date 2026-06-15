<?php

namespace App\Traits;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Convenience wrapper around ApiResponse for use inside controllers.
 */
trait ApiResponser
{
    protected function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $statusCode);
    }

    protected function created(mixed $data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return ApiResponse::success($data, $message, 201);
    }

    protected function noContent(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return ApiResponse::success(null, $message, 200);
    }

    protected function error(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        return ApiResponse::error($message, $statusCode, $errors);
    }
}
