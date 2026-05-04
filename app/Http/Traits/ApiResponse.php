<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Provides a uniform API response structure.
 *
 * Every response follows:
 * {
 *     "message": "...",
 *     "data": { ... } | null
 * }
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function error(string $message = 'Error', int $status = 422, mixed $data = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
