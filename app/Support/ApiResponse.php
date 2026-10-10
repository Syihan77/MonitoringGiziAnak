<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Envelope respons standar SKPL §5.4.1.
 *
 * Sukses: { success: true, data: {}, message: "string" }
 * Galat:  { success: false, error_code: "string", message: "string" }
 */
class ApiResponse
{
    public static function success(mixed $data = [], string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    public static function error(string $errorCode, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
        ], $extra), $status);
    }
}
