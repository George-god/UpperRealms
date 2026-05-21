<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Standard game AJAX envelope: { "success", "message", "data" }.
 */
final class GameJson
{
    public static function success(mixed $data = [], string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400, mixed $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
