<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Unified JSON response format for all API endpoints.
 *
 * @method static JsonResponse success(mixed $data, string $message = 'OK', int $code = 200)
 * @method static JsonResponse created(mixed $data, string $message = '创建成功')
 * @method static JsonResponse noContent(string $message = '操作成功')
 * @method static JsonResponse error(string $message, int $code = 400, array $errors = [])
 */
class ApiResponse
{
    /**
     * Success response with data payload.
     */
    public static function success(mixed $data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Resource created (HTTP 201).
     */
    public static function created(mixed $data = null, string $message = '创建成功'): JsonResponse
    {
        return static::success($data, $message, 201);
    }

    /**
     * Successful operation with no content body (HTTP 204 view).
     * Returns 200 with a message since 204 can't have a body.
     */
    public static function noContent(string $message = '操作成功'): JsonResponse
    {
        return static::success(null, $message, 200);
    }

    /**
     * Error response.
     */
    public static function error(string $message = '请求失败', int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }
}
