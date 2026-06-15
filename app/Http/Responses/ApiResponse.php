<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Builds the standard JSON envelope used across the API:
 *
 * Success: { "success": true,  "message": string, "data"?: mixed, "meta"?: array, "links"?: array }
 * Error:   { "success": false, "message": string, "errors"?: mixed }
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data instanceof ResourceCollection) {
            $resolved = $data->response()->getData(true);
            $payload['data'] = $resolved['data'] ?? [];

            if (isset($resolved['meta'])) {
                $payload['meta'] = $resolved['meta'];
            }

            if (isset($resolved['links'])) {
                $payload['links'] = $resolved['links'];
            }
        } elseif ($data instanceof JsonResource) {
            $resolved = $data->response()->getData(true);
            $payload['data'] = $resolved['data'] ?? $resolved;
        } elseif ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $statusCode);
    }

    public static function error(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode);
    }
}
