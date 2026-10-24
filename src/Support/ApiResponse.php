<?php

namespace Elyar\ServiceTokens\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * JSON envelope shared with the host apps' apiResponse() helper, kept here so
 * the package does not depend on it.
 */
class ApiResponse
{
    public static function success(mixed $data = null, int $code = 200, array $extra = [], ?string $message = null): JsonResponse
    {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $data ?? [],
        ], $extra), $code);
    }

    public static function error(string $error, int $code = 400, ?string $errorType = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $error,
            'error_type' => $errorType ?? strtolower(str_replace(' ', '_', Response::$statusTexts[$code])),
        ], $code);
    }
}
